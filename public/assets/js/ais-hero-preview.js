(function(){
  var examples={
    'store:sell':['TIENDA LOCAL · MUESTRA','Tienda local','Muestra visual conceptual para una tienda local.'],
    'store:inquiries':['TIENDA LOCAL · MUESTRA','Tienda local','Muestra visual conceptual para presentar una tienda y facilitar consultas.'],
    'store:bookings':['TIENDA LOCAL · MUESTRA','Tienda local','Muestra visual conceptual con información para solicitar una cita.'],
    'restaurant:sell':['RESTAURANTE · MUESTRA','Restaurante','Muestra visual conceptual de una página para restaurante.'],
    'restaurant:inquiries':['RESTAURANTE · MUESTRA','Restaurante','Muestra visual conceptual de un restaurante con información de contacto.'],
    'restaurant:bookings':['RESTAURANTE · MUESTRA','Restaurante','Muestra visual conceptual para presentar un restaurante y recibir solicitudes.'],
    'services:sell':['SERVICIOS · MUESTRA','Servicios profesionales','Muestra visual conceptual de una página de servicios profesionales.'],
    'services:inquiries':['SERVICIOS · MUESTRA','Servicios profesionales','Muestra visual conceptual para organizar la información de servicios.'],
    'services:bookings':['SERVICIOS · MUESTRA','Servicios profesionales','Muestra visual conceptual para presentar servicios y recibir solicitudes.']
  };
  document.querySelectorAll('[data-wwi-ais-preview]').forEach(function(root){
    if(root.dataset.previewReady==='1')return;
    root.dataset.previewReady='1';
    function update(){
      var business=root.querySelector('[data-preview-group="business"] [aria-pressed="true"]');
      var goal=root.querySelector('[data-preview-group="goal"] [aria-pressed="true"]');
      var example=examples[(business?business.dataset.previewValue:'store')+':'+(goal?goal.dataset.previewValue:'sell')];
      if(!example)return;
      root.querySelector('[data-preview-kicker]').textContent=example[0];
      root.querySelector('[data-preview-title]').textContent=example[1];
      root.querySelector('[data-preview-description]').textContent=example[2];
      root.querySelector('[data-preview-announcement]').textContent=example[0]+'. '+example[1]+'. '+example[2];
    }
    root.querySelectorAll('[data-preview-group]').forEach(function(group){
      group.addEventListener('click',function(event){
        var button=event.target.closest('button[data-preview-value]');
        if(!button)return;
        group.querySelectorAll('button[data-preview-value]').forEach(function(option){option.setAttribute('aria-pressed',option===button?'true':'false')});
        update();
      });
    });
    update();
  });
})();