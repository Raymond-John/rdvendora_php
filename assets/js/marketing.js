(function () {
  var heroVideo = document.getElementById('hero-bg-video');
  if (heroVideo) {
    var sources = [
      'pinterest_video_1780670597 (1).mp4',
      'pinterest_video_1780673033.mp4'
    ];
    var i = 0;
    var playAt = function (idx) {
      heroVideo.src = sources[idx];
      heroVideo.load();
      heroVideo.play().catch(function () {});
    };
    playAt(0);
    heroVideo.addEventListener('ended', function () {
      i = (i + 1) % sources.length;
      playAt(i);
    });
    heroVideo.addEventListener('error', function () {
      i = (i + 1) % sources.length;
      playAt(i);
    });
  }

  window.switchBilling = function (btn, mode) {
    document.querySelectorAll('.mk-tabs .tab-btn').forEach(function (el) { el.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    document.querySelectorAll('.monthly-price').forEach(function (el) { el.classList.toggle('hidden', mode === 'annual'); });
    document.querySelectorAll('.annual-price').forEach(function (el) { el.classList.toggle('hidden', mode !== 'annual'); });
  };

  var modal = document.getElementById('reviewModal');
  var openBtn = document.getElementById('writeReviewBtn');
  var closeBtn = document.getElementById('closeModalBtn');
  var cancelBtn = document.getElementById('cancelModalBtn');
  function closeModal() { if (modal) modal.classList.remove('is-open'); }
  if (openBtn && modal) openBtn.addEventListener('click', function () { modal.classList.add('is-open'); });
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
  if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

  var stars = document.getElementById('ratingStars');
  var ratingInput = document.getElementById('ratingValue');
  if (stars && ratingInput) {
    var paint = function (n) {
      stars.querySelectorAll('span').forEach(function (s) {
        s.style.color = Number(s.getAttribute('data-val')) <= n ? '#f59e0b' : '#cbd5e1';
      });
    };
    paint(5);
    stars.addEventListener('click', function (e) {
      var t = e.target.closest('span');
      if (!t) return;
      ratingInput.value = t.getAttribute('data-val');
      paint(Number(ratingInput.value));
    });
  }
})();
