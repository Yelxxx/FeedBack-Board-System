/* ============================================================================
   OCT Feedback Board System — front-end interactions
   ============================================================================ */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {

    /* ---- Mobile navigation ---- */
    var toggle = document.getElementById('navToggle');
    var links = document.getElementById('navLinks');
    if (toggle && links) {
      toggle.addEventListener('click', function () {
        var open = links.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    /* ---- Notifications ---- */
    var notifBtn = document.getElementById('notifBtn');
    var notifPanel = document.getElementById('notifPanel');
    if (notifBtn && notifPanel) {
      notifBtn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        var open = notifPanel.classList.toggle('open');
        if (open) markNotificationsRead();
      });
      document.addEventListener('click', function (ev) {
        if (!ev.target.closest('.notif')) notifPanel.classList.remove('open');
      });
      document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') notifPanel.classList.remove('open');
      });
    }

    function markNotificationsRead() {
      var base = document.body.getAttribute('data-base') || '';
      var token = document.body.getAttribute('data-csrf') || '';
      fetch(base + 'notifications_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(token)
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.ok) {
            var dot = document.querySelector('.notif-dot');
            if (dot) dot.remove();
            var items = notifPanel.querySelectorAll('.notif-item');
            for (var i = 0; i < items.length; i++) items[i].classList.add('read');
          }
        })
        .catch(function () { /* ignore */ });
    }

    /* ---- Alerts: auto-dismiss success alerts, keep error alerts visible ---- */
    document.querySelectorAll('.alert-success').forEach(function (alert) {
      setTimeout(function () {
        alert.classList.add('hide');
        setTimeout(function () { alert.remove(); }, 320);
      }, 6500);
    });
    document.querySelectorAll('.alert-close').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var alert = btn.closest('.alert');
        if (!alert) return;
        alert.classList.add('hide');
        setTimeout(function () { alert.remove(); }, 320);
      });
    });

    /* ---- Character counters ---- */
    document.querySelectorAll('[data-count]').forEach(function (input) {
      var counter = document.getElementById(input.getAttribute('data-count'));
      if (!counter) return;
      var update = function () {
        counter.textContent = input.value.length + ' / ' + input.maxLength;
      };
      input.addEventListener('input', update);
      update();
    });

    /* ---- Anonymous toggle note ---- */
    var anon = document.getElementById('isAnonymous');
    var anonNote = document.getElementById('anonNote');
    if (anon && anonNote) {
      var sync = function () { anonNote.hidden = !anon.checked; };
      anon.addEventListener('change', sync);
    }

    /* ---- Multi-file media input: names + thumbnails ---- */
    var fileInput = document.getElementById('imageInput');
    if (fileInput) {
      var maxFiles = parseInt(fileInput.getAttribute('data-max-files') || '3', 10);
      var maxMbAttr = document.body.getAttribute('data-max-upload') || '5';
      fileInput.setAttribute('data-max-files', maxFiles);
      fileInput.addEventListener('change', function () {
        var nameEl = document.getElementById('fileName');
        var wrap = document.getElementById('mediaPreview');
        var files = Array.prototype.slice.call(fileInput.files).slice(0, maxFiles);
        if (nameEl) {
          nameEl.textContent = files.length
            ? files.length + ' file(s) selected (max ' + maxFiles + ', ' + maxMbAttr + ' MB each)'
            : 'Choose files (max ' + maxMbAttr + ' MB each, ' + maxFiles + ' max)';
        }
        if (wrap) {
          wrap.innerHTML = '';
          files.forEach(function (file) {
            var name = document.createElement('span');
            name.className = 'chip-name';
            name.textContent = file.name;
            if (file.type.indexOf('image/') === 0) {
              var img = document.createElement('img');
              img.className = 'media-thumb';
              img.alt = file.name;
              img.src = URL.createObjectURL(file);
              wrap.appendChild(img);
            } else {
              var chip = document.createElement('span');
              chip.className = 'media-chip';
              chip.appendChild(name);
              wrap.appendChild(chip);
            }
          });
          if (fileInput.files.length > maxFiles && nameEl) {
            nameEl.textContent += ' — only the first ' + maxFiles + ' will be uploaded';
          }
        }
      });
    }

    /* ---- Registration: role cards sync the department field ---- */
    var roleRadios = document.querySelectorAll('input[name="role"]');
    var deptLabel = document.getElementById('deptLabel');
    var deptInput = document.getElementById('department');
    var deptMeta = {
      student: { label: 'Course / Program', placeholder: 'e.g. BS Computer Science' },
      faculty: { label: 'Department / College', placeholder: 'e.g. College of Engineering' },
      staff:   { label: 'Office / Unit',     placeholder: 'e.g. Registrar Office' }
    };
    if (roleRadios.length && deptLabel && deptInput) {
      var syncRole = function () {
        var checked = document.querySelector('input[name="role"]:checked');
        if (!checked || !deptMeta[checked.value]) return;
        deptLabel.textContent = deptMeta[checked.value].label;
        deptInput.placeholder = deptMeta[checked.value].placeholder;
      };
      roleRadios.forEach(function (radio) { radio.addEventListener('change', syncRole); });
      syncRole();
    }

    /* ---- Login: demo account quick-fill buttons ---- */
    document.querySelectorAll('[data-demo-email]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var email = document.getElementById('email');
        var pass = document.getElementById('password');
        if (email) email.value = btn.getAttribute('data-demo-email');
        if (pass) pass.value = btn.getAttribute('data-demo-password');
        if (email) email.focus();
      });
    });

    /* ---- Chart bars: animate in ---- */
    document.querySelectorAll('.chart-fill').forEach(function (bar) {
      var target = parseInt(bar.getAttribute('data-w') || '0', 10);
      var width = target > 0 && target < 4 ? 4 : target; // keep tiny values visible
      setTimeout(function () { bar.style.width = width + '%'; }, 150);
    });

    /* ---- Confirm dialogs on dangerous forms ---- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (ev) {
        if (!window.confirm(form.getAttribute('data-confirm'))) ev.preventDefault();
      });
    });
  });
})();
