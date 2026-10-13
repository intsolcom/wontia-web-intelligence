<?php
namespace App\Widgets;

use App\Services\CustomerService;
use App\Services\ReviewService;

class ProductReviewsWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'product-reviews', 'name' => 'Reseñas de producto', 'icon' => '⭐', 'category' => 'ecommerce', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'product_ref', 'label' => 'Referencia del producto', 'type' => 'text', 'help' => 'Handle/SKU del producto.'],
            ['key' => 'title', 'label' => 'Título', 'type' => 'text', 'default' => 'Opiniones de clientes'],
            ['key' => 'limit', 'label' => 'Máximo de reseñas', 'type' => 'number', 'default' => 10],
            ['key' => 'show_form', 'label' => 'Permitir escribir reseña', 'type' => 'toggle', 'default' => 1],
        ];
    }

    public static function defaultConfig(): array
    {
        return ['product_ref' => '', 'title' => 'Opiniones de clientes', 'limit' => 10, 'show_form' => 1];
    }

    public static function adminPreview(): string
    {
        return '<div style="padding:18px;border:1px dashed #ccc;border-radius:8px;color:#666">⭐ Reseñas de producto: promedio, distribución, listado y formulario.</div>';
    }

    private function stars(float $avg): string
    {
        $full = (int)round($avg);
        $out = '';
        for ($i = 1; $i <= 5; $i++) $out .= '<span style="color:' . ($i <= $full ? '#F5A623' : '#DDD') . '">★</span>';
        return $out;
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $ref = trim((string)($c['product_ref'] ?? ''));
        if ($ref === '') return '';
        $title = $this->esc((string)($c['title'] ?? 'Opiniones de clientes'));
        $limit = (int)($c['limit'] ?? 10) ?: 10;
        $showForm = !empty($c['show_form']);
        $refEsc = $this->esc($ref);
        $uid = 'prw' . substr(md5($ref . '|' . $limit), 0, 6);

        $svc = new ReviewService();
        $summary = $svc->summary($ref);
        $items = $svc->forProduct($ref, $limit);
        $me = (new CustomerService())->current();
        $canWrite = false;
        if ($me && $showForm && !$svc->alreadyReviewed((int)$me['id'], $ref)) $canWrite = true;

        $h = '<div class="prw" id="' . $uid . '" data-ref="' . $refEsc . '">';
        $h .= '<style>'
            . '.prw{max-width:960px;margin:28px auto;padding:0 16px;font-family:inherit}'
            . '.prw h3{margin:0 0 14px}'
            . '.prw-sum{display:flex;gap:26px;flex-wrap:wrap;align-items:center;padding:16px;border:1px solid #eee;border-radius:12px;background:#fff}'
            . '.prw-avg{text-align:center;min-width:120px}.prw-avg b{font-size:34px;line-height:1}'
            . '.prw-bar{display:flex;align-items:center;gap:8px;font-size:12px;color:#777;margin:2px 0}'
            . '.prw-bar i{flex:1;height:8px;background:#eee;border-radius:6px;overflow:hidden;display:block;min-width:120px}'
            . '.prw-bar i b{display:block;height:100%;background:#F5A623}'
            . '.prw-item{padding:14px 0;border-bottom:1px solid #eee}'
            . '.prw-item .top{display:flex;justify-content:space-between;gap:10px;align-items:baseline}'
            . '.prw-ver{font-size:11px;background:#D9F2E2;color:#1E7A4B;padding:2px 8px;border-radius:20px}'
            . '.prw-form{margin-top:20px;padding:16px;border:1px solid #eee;border-radius:12px;background:#fff}'
            . '.prw-form input,.prw-form textarea,.prw-form select{width:100%;box-sizing:border-box;padding:9px 10px;margin:5px 0;border:1px solid #ddd;border-radius:8px;font:inherit}'
            . '.prw-form button{padding:10px 18px;border:0;border-radius:8px;background:#111;color:#fff;font-weight:600;cursor:pointer}'
            . '.prw-stars span{cursor:pointer;font-size:26px;color:#DDD;user-select:none}'
            . '.prw-msg{margin-top:8px;font-size:13px}'
            . '</style>';

        $h .= '<h3>' . $title . '</h3>';
        $h .= '<div class="prw-sum">';
        $h .= '<div class="prw-avg"><b>' . ($summary['count'] ? number_format($summary['average'], 1) : '—') . '</b><div>' . $this->stars((float)$summary['average']) . '</div><small style="color:#777">' . (int)$summary['count'] . ' reseña(s)</small></div>';
        $h .= '<div style="flex:1;min-width:200px">';
        foreach ([5, 4, 3, 2, 1] as $n) {
            $cnt = (int)($summary['dist'][$n] ?? 0);
            $pct = $summary['count'] ? round($cnt * 100 / $summary['count']) : 0;
            $h .= '<div class="prw-bar"><span>' . $n . '★</span><i><b style="width:' . $pct . '%"></b></i><span>' . $cnt . '</span></div>';
        }
        $h .= '</div></div>';

        if (!$items) {
            $h .= '<p style="color:#777;margin-top:14px">Aún no hay reseñas. ¡Sé el primero!</p>';
        } else {
            foreach ($items as $r) {
                $h .= '<div class="prw-item"><div class="top"><div><b>' . $this->esc((string)($r['title'] ?: 'Reseña')) . '</b> ' . $this->stars((float)$r['rating'])
                    . ' ' . (!empty($r['verified_purchase']) ? '<span class="prw-ver">Compra verificada</span>' : '') . '</div>'
                    . '<small style="color:#999">' . $this->esc(substr((string)$r['created_at'], 0, 10)) . '</small></div>'
                    . '<div style="margin:6px 0;color:#444">' . nl2br($this->esc((string)$r['body'])) . '</div>'
                    . '<small style="color:#777">— ' . $this->esc((string)$r['author_name']) . ' · <a href="#" class="prw-help" data-id="' . (int)$r['id'] . '" style="color:#777">Útil (' . (int)$r['helpful'] . ')</a></small></div>';
            }
        }

        if ($canWrite) {
            $h .= '<div class="prw-form"><h4 style="margin:0 0 6px">Escribe tu reseña</h4>'
                . '<div class="prw-stars" id="' . $uid . '-stars" data-v="5">' . $this->stars(5) . '</div>'
                . '<input type="text" id="' . $uid . '-title" placeholder="Título (opcional)" maxlength="120" />'
                . '<textarea id="' . $uid . '-body" rows="3" placeholder="¿Qué te pareció el producto?" maxlength="2000"></textarea>'
                . '<button type="button" id="' . $uid . '-send">Publicar reseña</button>'
                . '<div class="prw-msg" id="' . $uid . '-msg"></div></div>';
        } elseif ($me && $showForm && $svc->alreadyReviewed((int)$me['id'], $ref)) {
            $h .= '<p style="color:#777;margin-top:14px">Gracias, ya enviaste tu reseña para este producto.</p>';
        } elseif ($showForm) {
            $h .= '<p style="margin-top:14px"><a href="/cuenta?tab=reviews" style="font-weight:600">Inicia sesión para escribir una reseña →</a></p>';
        }

        $h .= '</div>';

        $h .= '<script>(function(){var root=document.getElementById("' . $uid . '");if(!root)return;'
            . 'root.querySelectorAll(".prw-help").forEach(function(a){a.addEventListener("click",function(e){e.preventDefault();var id=a.getAttribute("data-id");'
            . 'wontia&&wontia.api?(wontia.api("/api/v1/public/store/reviews/"+id+"/helpful",{method:"POST",body:{}}).then(function(r){if(r&&r.ok){var n=a.textContent.replace(/[^0-9]/g,"");a.textContent="Útil ("+(parseInt(n||0,1)+1)+")"}})):0;});});'
            . 'var st=document.getElementById("' . $uid . '-stars");if(st){var setStars=function(v){st.setAttribute("data-v",v);st.innerHTML="";for(var i=1;i<=5;i++){var s=document.createElement("span");s.textContent="\\u2605";s.style.color=i<=v?"#F5A623":"#DDD";s.addEventListener("click",function(){setStars(parseInt(this.getAttribute("data-i")))});s.setAttribute("data-i",i);st.appendChild(s)}};setStars(5);'
            . 'var btn=document.getElementById("' . $uid . '-send");if(btn){btn.addEventListener("click",function(){var msg=document.getElementById("' . $uid . '-msg");'
            . 'var body=document.getElementById("' . $uid . '-body").value.trim();if(body.length<5){msg.textContent="Escribe al menos unas palabras.";msg.style.color="#DC2626";return;}'
            . 'btn.disabled=true;msg.style.color="#777";msg.textContent="Enviando...";'
            . 'fetch("/api/v1/public/account/reviews",{method:"POST",headers:{"Content-Type":"application/json"},credentials:"same-origin",body:JSON.stringify({product_ref:root.getAttribute("data-ref"),rating:parseInt(st.getAttribute("data-v")),title:document.getElementById("' . $uid . '-title").value,body:body})})'
            . '.then(function(r){return r.json()}).then(function(r){if(r&&r.ok){msg.style.color="#1E7A4B";msg.textContent=r.message||"¡Gracias!";setTimeout(function(){location.reload()},900)}else{btn.disabled=false;msg.style.color="#DC2626";msg.textContent=(r&&(r.message||(r.data&&r.data.message)))||"No se pudo publicar."}}).catch(function(){btn.disabled=false;msg.style.color="#DC2626";msg.textContent="Error de red."});});}'
            . '}})();</script>';

        return $h;
    }
}
