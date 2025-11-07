// assets/js/core.js
document.addEventListener('DOMContentLoaded', function(){
  var btn = document.getElementById('menuToggle');
  if(btn){
    btn.addEventListener('click', function(){ 
      var sb = document.querySelector('.sidebar');
      if(sb) sb.style.display = sb.style.display === 'none' ? 'block' : 'none';
    });
  }

  var configToggles = document.querySelectorAll('[data-toggle="config"]');
  configToggles.forEach(function(toggle) {
    toggle.addEventListener('click', function(e) {
      e.preventDefault();
      var target = document.querySelector(this.getAttribute('data-target'));
      if (target) {
        target.style.display = target.style.display === 'none' ? 'table-row' : 'none';
      }
    });
  });
});
