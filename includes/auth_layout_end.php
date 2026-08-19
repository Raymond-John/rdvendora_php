      </div>
    </div>
  </div>
<?php require __DIR__ . '/site_footer.php'; ?>
  <script src="assets/js/rdv-public.js" defer></script>
  <script>
    function checkStrength(pw) {
      var el = document.getElementById('password-strength');
      if (!el) return;
      el.style.display = pw ? 'block' : 'none';
      var score = 0;
      if (pw.length >= 8) score++;
      if (/[A-Z]/.test(pw)) score++;
      if (/[0-9]/.test(pw)) score++;
      if (/[^A-Za-z0-9]/.test(pw)) score++;
      var classes = ['', 'weak', 'fair', 'good', 'strong'];
      var texts = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'];
      for (var i = 1; i <= 4; i++) {
        var seg = document.getElementById('s' + i);
        if (!seg) continue;
        seg.className = i <= score ? 'strength-segment ' + classes[score] : 'strength-segment';
      }
      var strengthText = document.getElementById('strength-text');
      if (strengthText) strengthText.textContent = texts[score] || 'Password strength';
    }
    function togglePassword(fieldId, btn) {
      var field = document.getElementById(fieldId);
      if (!field) return;
      var hide = field.type === 'password';
      field.type = hide ? 'text' : 'password';
      if (btn) btn.setAttribute('aria-label', hide ? 'Hide password' : 'Show password');
    }
  </script>
</body>
</html>
