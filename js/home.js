/* Home da Destrava: menu do celular e fundo animado do topo */
(function(){
  var b=document.getElementById('menu-btn'),m=document.getElementById('menu');
  b.addEventListener('click',function(){var a=document.body.classList.toggle('menu-aberto');b.setAttribute('aria-expanded',a);});
  m.addEventListener('click',function(e){if(e.target.tagName==='A'){document.body.classList.remove('menu-aberto');b.setAttribute('aria-expanded','false');}});
})();

window.addEventListener('load',function(){
  if(matchMedia('(prefers-reduced-motion: reduce)').matches||!window.VANTA) return;
  var cel=innerWidth<760;
  var semMouse=cel||matchMedia('(hover: none)').matches;
  var fx=window.VANTA.NET({el:'#hero-fx',mouseControls:!semMouse,touchControls:false,gyroControls:false,minHeight:200,minWidth:200,scale:1,scaleMobile:1,
    color:0xb89452,backgroundColor:0x182a3a,points:cel?7:11,maxDistance:cel?18:22,spacing:cel?18:16,showDots:true});
  document.body.classList.add('fx-on');
  // Celular e tablet: sem mouse, a rede sobe e desce sozinha e acompanha a rolagem
  if(semMouse&&fx&&fx.onMouseMove){
    var t0=performance.now();
    (function mexe(t){
      var k=(t-t0)/1000;
      var y=0.5+0.35*Math.sin(k*0.9)-Math.min(scrollY/innerHeight,1)*0.4;
      var x=0.5+0.18*Math.sin(k*0.45);
      fx.onMouseMove(Math.max(0,Math.min(1,x)),Math.max(0,Math.min(1,y)));
      requestAnimationFrame(mexe);
    })(t0);
  }
});

/* Carrossel de cases: só liga com 2 ou mais cases */
(function(){
  var trilho=document.getElementById('casos-trilho'); if(!trilho) return;
  var cards=trilho.querySelectorAll('.caso-card'); if(cards.length<2) return;
  var nav=trilho.parentNode.querySelector('.casos-nav'), pontos=nav.querySelector('.casos-pontos');
  var setas=nav.querySelectorAll('.casos-seta');
  nav.hidden=false;
  function atual(){ return Math.round(trilho.scrollLeft/trilho.clientWidth); }
  function vai(i){ i=Math.max(0,Math.min(cards.length-1,i)); trilho.scrollTo({left:cards[i].offsetLeft-trilho.offsetLeft,behavior:'smooth'}); }
  cards.forEach(function(c,i){ var b=document.createElement('button'); b.type='button'; b.setAttribute('aria-label','Case '+(i+1)); b.addEventListener('click',function(){vai(i);}); pontos.appendChild(b); });
  function marca(){ var a=atual(); pontos.querySelectorAll('button').forEach(function(b,i){ b.setAttribute('aria-current',i===a?'true':'false'); }); setas[0].disabled=a===0; setas[1].disabled=a===cards.length-1; }
  setas.forEach(function(s){ s.addEventListener('click',function(){ vai(atual()+Number(s.dataset.dir)); }); });
  var t; trilho.addEventListener('scroll',function(){ clearTimeout(t); t=setTimeout(marca,80); });
  marca();
})();

/* Como funciona: fases acendem conforme a rolagem */
(function(){
  var ol=document.getElementById('fases'); if(!ol) return;
  if(matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var fases=ol.querySelectorAll('.fase'), n=fases.length;
  ol.classList.add('anima');
  function atualiza(){
    var r=ol.getBoundingClientRect(), h=innerHeight;
    var p=innerWidth>=900
      ? (h*0.85-r.top)/(h*0.4)              /* computador: linha deitada, acende enquanto os cards entram na tela */
      : (h*0.8-r.top)/(r.height*0.9+h*0.25); /* celular: linha em pé, acompanha a rolagem pelos cards */
    p=Math.max(0,Math.min(1,p));
    ol.style.setProperty('--prog',p.toFixed(3));
    fases.forEach(function(f,i){ f.classList.toggle('aceso', p>=i/(n-1)-0.01 && p>0); });
  }
  addEventListener('scroll',atualiza,{passive:true}); addEventListener('resize',atualiza); atualiza();
})();

/* Serviços: os três cards do funil acendem em sequência */
(function(){
  var funil=document.querySelector('.funil'); if(!funil||!('IntersectionObserver' in window)) return;
  if(matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var etapas=funil.querySelectorAll('.etapa');
  funil.classList.add('anima');
  var io=new IntersectionObserver(function(es){
    es.forEach(function(e){
      if(!e.isIntersecting) return;
      var i=Array.prototype.indexOf.call(etapas,e.target);
      var atraso=innerWidth>=960?i*220:0;
      setTimeout(function(){ e.target.classList.add('visto'); },atraso);
      io.unobserve(e.target);
    });
  },{threshold:0.2});
  etapas.forEach(function(et){ io.observe(et); });
})();

/* Nossos cases: card entra animado ao aparecer */
(function(){
  var trilho=document.getElementById('casos-trilho'); if(!trilho||!('IntersectionObserver' in window)) return;
  if(matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var cards=trilho.querySelectorAll('.caso-card'); trilho.classList.add('anima');
  var io=new IntersectionObserver(function(es){ es.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('visto'); io.unobserve(e.target);} }); },{threshold:0.2});
  cards.forEach(function(c){ io.observe(c); });
})();
