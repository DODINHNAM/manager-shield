// assets/js/core.js
document.addEventListener('DOMContentLoaded', function(){
  var btn = document.getElementById('menuToggle');
  if(btn){
    btn.addEventListener('click', function(){ 
      var sb = document.querySelector('.sidebar');
      if(sb) sb.style.display = sb.style.display === 'none' ? 'block' : 'none';
    });
  }
});
