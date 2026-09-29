(function(){
  var examples={
    'store:sell':['TIENDA · EJEMPLO DE EXPERIENCIA','Luna','Una muestra conceptual de presencia digital para una marca de bienestar.'],
    'store:inquiries':['TIENDA · EJEMPLO DE EXPERIENCIA','Luna','Una muestra conceptual para orientar consultas sobre productos y servicios.'],
    'store:bookings':['TIENDA · EJEMPLO DE EXPERIENCIA','Luna','Una muestra conceptual de cómo presentar opciones para una cita.'],
    'restaurant:sell':['RESTAURANTE · EJEMPLO DE EXPERIENCIA','Mesa Clara','Una muestra conceptual para presentar un menú y facilitar pedidos.'],
    'restaurant:inquiries':['RESTAURANTE · EJEMPLO DE EXPERIENCIA','Mesa Clara','Una muestra conceptual para responder preguntas y recibir consultas.'],
    'restaurant:bookings':['RESTAURANTE · EJEMPLO DE EXPERIENCIA','Mesa Clara','Una muestra conceptual para explicar una experiencia y solicitar una reserva.'],
    'services:sell':['SERVICIOS · EJEMPLO DE EXPERIENCIA','Estudio Norte','Una muestra conceptual para presentar servicios y facilitar solicitudes.'],
    'services:inquiries':['SERVICIOS · EJEMPLO DE EXPERIENCIA','Estudio Norte','Una muestra conceptual para orientar consultas según cada necesidad.'],
    'services:bookings':['SERVICIOS · EJEMPLO DE EXPERIENCIA','Estudio Norte','Una muestra conceptual para describir un servicio y solicitar una cita.']
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