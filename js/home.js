/* Home da Destrava: menu do celular e fundo animado do topo */
(function(){
  var b=document.getElementById('menu-btn'),m=document.getElementById('menu');
  b.addEventListener('click',function(){var a=document.body.classList.toggle('menu-aberto');b.setAttribute('aria-expanded',a);});
  m.addEventListener('click',function(e){if(e.target.tagName==='A'){document.body.classList.remove('menu-aberto');b.setAttribute('aria-expanded','false');}});
})();

window.addEventListener('load',function(){
  if(matchMedia('(prefers-reduced-motion: reduce)').matches||!window.VANTA) return;
  var cel=innerWidth<760;
  window.VANTA.NET({el:'#hero-fx',mouseControls:!cel,touchControls:false,gyroControls:false,minHeight:200,minWidth:200,scale:1,scaleMobile:1,
    color:0xb89452,backgroundColor:0x182a3a,points:cel?7:11,maxDistance:cel?18:22,spacing:cel?18:16,showDots:true});
  document.body.classList.add('fx-on');
});
