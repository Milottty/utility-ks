(function () {
  'use strict';
  // galeria e produktit
  var main = document.getElementById('gmain');
  var thumbs = document.querySelectorAll('.gthumbs button');
  thumbs.forEach(function (b) {
    b.addEventListener('click', function () {
      if (main) main.src = b.dataset.src;
      thumbs.forEach(function (x) { x.classList.remove('on'); });
      b.classList.add('on');
    });
  });
  // sasia + / -
  document.querySelectorAll('.qty').forEach(function (q) {
    var input = q.querySelector('input');
    q.querySelectorAll('button').forEach(function (b) {
      b.addEventListener('click', function () {
        var v = (parseInt(input.value, 10) || 1) + parseInt(b.dataset.d, 10);
        var max = parseInt(input.max, 10) || 999;
        input.value = Math.min(Math.max(1, v), max);
      });
    });
  });
})();
