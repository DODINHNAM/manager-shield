// assets/js/core.js
document.addEventListener('DOMContentLoaded', function(){
  var btn = document.getElementById('menuToggle');
  if(btn){
    btn.addEventListener('click', function(){ 
      var sb = document.querySelector('.sidebar');
      if(sb) sb.style.display = sb.style.display === 'none' ? 'block' : 'none';
    });
  }

  // Modal handling
  var modal = document.getElementById("configModal");
  var modalTitle = document.getElementById("modalTitle");
  var modalBody = document.getElementById("modalBody");
  var span = document.getElementsByClassName("close")[0];

  document.addEventListener('click', function(e) {
    if (e.target && e.target.matches('[data-toggle="modal"]')) {
      e.preventDefault();
      
      var title = e.target.getAttribute('data-title');
      var targetSelector = e.target.getAttribute('data-target');
      var formContent = document.querySelector(targetSelector).cloneNode(true);

      modalTitle.textContent = title;
      modalBody.innerHTML = '';
      modalBody.appendChild(formContent);

      modal.style.display = "block";
    }
  });

  span.onclick = function() {
    modal.style.display = "none";
    modalBody.innerHTML = '';
  }

  window.onclick = function(event) {
    if (event.target == modal) {
      modal.style.display = "none";
      modalBody.innerHTML = '';
    }
  }
});
