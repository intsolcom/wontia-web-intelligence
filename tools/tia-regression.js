const base = process.env.TIA_BASE || 'https://sonika.com.co';
let cookie = '';
let failures = 0;

function assert(name, ok, detail) {
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail ? ' :: ' + detail : ''));
  if (!ok) failures++;
}

async function chat(message) {
  const res = await fetch(base + '/api/v1/public/tia/chat', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...(cookie ? { Cookie: cookie } : {}) },
    body: JSON.stringify({ message, page: '/tienda', locale: 'es' })
  });
  const cookies = res.headers.getSetCookie ? res.headers.getSetCookie() : [];
  if (cookies.length) cookie = cookies.map(c => c.split(';')[0]).join('; ');
  return res.json();
}

(async () => {
  const cheap = await chat('un piano barato');
  assert('barato responde', cheap.ok === true);
  assert('barato muestra CDP-S160', /CDP-S160/i.test(cheap.reply || ''), cheap.reply);
  assert('barato muestra el más económico', (cheap.cards || [])[0]?.price_cents === 202960000, String((cheap.cards || [])[0]?.price_cents));
  assert('barato tiene imagen', !!(cheap.cards || [])[0]?.image);
  assert('sin fuga interna', !/CIU|Centro de Información|base de conocimiento/i.test(cheap.reply || ''));

  const compare = await chat('compara los dos');
  assert('comparador responde', !!compare.comparison && (compare.comparison.products || []).length === 2);
  assert('comparador muestra precio', (compare.comparison.rows || []).some(r => r.label === 'Precio'));
  assert('comparador muestra disponibilidad', (compare.comparison.rows || []).some(r => r.label === 'Disponibilidad'));

  const model = await chat('cuanto cuesta el AP-470');
  assert('modelo directo responde', /AP-470/i.test(model.reply || ''));
  assert('modelo directo trae tarjeta', (model.cards || []).some(c => /AP-470/i.test(c.title || '')));

  const image = await chat('dame imagenes');
  assert('imagen responde con tarjetas', (image.cards || []).length > 0);
  assert('imagen sí se muestra', (image.cards || []).every(c => !!c.image));
  assert('no niega imágenes', !/no puedo enviar imágenes/i.test(image.reply || ''));

  const noWa = await chat('no quiero ir a whatsapp');
  assert('respeta canal', noWa.handoff?.kind === 'email' || !/whatsapp/i.test(noWa.handoff?.url || ''));

  const farewell = await chat('me voy de aqui, no sirves para nada');
  assert('cierre empático', /lamento|frustración|no voy a insistir/i.test(farewell.reply || ''));
  assert('handoff con resumen', !!farewell.handoff?.summary);
  assert('handoff sin whatsapp', farewell.handoff?.kind === 'email');

  process.exit(failures ? 1 : 0);
})();
