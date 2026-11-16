/**
 * DerZ Admin, on the DerZ app's theme (jQuery, Bootstrap 3, SweetAlert 2 as in the app):
 *  - form.js-form is sent in the background; errors show under their field (.form-group.has-error
 *    span.error, as in the app), other messages as the app's toasts;
 *  - [data-post="url"] buttons: data-confirm (a SweetAlert question), data-prompt (asks for a text,
 *    posted as data-field), data-value-x="y" (posted as x=y);
 *  - Bootstrap tabs and modals (data-toggle), the hash keeps the open tab;
 *  - night mode (html.dark, kept in the browser), canvas[data-chart] charts.
 * Answers are JSON: {status, url, message, errors: {field: text}, password}.
 */
(function ($) {
  "use strict";
  var csrf = { name: $('meta[name="csrf-name"]').attr("content"), hash: $('meta[name="csrf-hash"]').attr("content") };
  var esc = function (t) { return $("<div>").text(t == null ? "" : String(t)).html(); };

  /* ---------- the app's toast ---------- */
  function toast(message, type) {
    type = type === "error" ? "error" : (type || "success");
    var icons = { success: "fa-check", error: "fa-times", warning: "fa-exclamation", info: "fa-info" };
    var duration = type === "error" ? 8000 : 5000;
    swal({
      toast: true, position: "top-end", showConfirmButton: false, showCloseButton: true, animation: false, timer: duration,
      customClass: "derz-toast derz-toast-" + type,
      html: '<div class="derz-toast-body"><span class="derz-toast-icon"><i class="fas ' + icons[type] + '"></i></span><div class="derz-toast-text"><div class="derz-toast-title">'
        + esc(message) + '</div></div></div><div class="derz-toast-progress"><span style="animation-duration:' + duration + 'ms"></span></div>',
    });
  }
  window.derzToast = toast;

  function confirmBox(text, opts) {
    return swal($.extend({
      title: text, type: "warning", showCancelButton: true, buttonsStyling: false,
      confirmButtonClass: "btn btn-default swal2-btn-default", cancelButtonClass: "btn btn-default swal2-btn-default",
      confirmButtonText: "Yes, continue", cancelButtonText: "Cancel",
    }, opts || {}));
  }

  /* a password shown once (new staff, reset) */
  function showSecret(message, secret) {
    swal({
      title: "Password", type: "info", buttonsStyling: false, confirmButtonClass: "btn btn-default swal2-btn-default", confirmButtonText: "Done",
      html: '<p>' + esc(message || "") + '</p><p><code style="font-size:18px;padding:8px 12px;display:inline-block;user-select:all">' + esc(secret)
        + '</code></p><p class="text-muted">Shown only now. Send it privately.</p>',
    }).then(function () { location.reload(); });
  }

  /* ---------- sending ---------- */
  function send(url, data, done) {
    if (csrf.name && !data.has(csrf.name)) { data.append(csrf.name, csrf.hash); }
    fetch(url, { method: "POST", body: data, headers: { "X-Requested-With": "XMLHttpRequest" }, credentials: "same-origin" })
      .then(function (r) { return r.json().catch(function () { return { status: "fail", message: "The server answered with an error (" + r.status + ")." }; }); })
      .then(done)
      .catch(function () { done({ status: "fail", message: "No connection. Try again." }); });
  }

  function handle(res, $form) {
    if (res.login) { location.href = document.body.getAttribute("data-base") + "login"; return; }
    if ($form) {
      $form.find(".has-error").removeClass("has-error");
      $form.find("span.error.js-error").remove();
    }
    if (res.status === "success") {
      if (res.password) { $(".modal.in").modal("hide"); showSecret(res.message, res.password); return; }
      if (res.url) {
        if (res.url.split("#")[0] === location.href.split("#")[0]) { location.hash = res.url.split("#")[1] || ""; location.reload(); } else { location.href = res.url; }
        return;
      }
      toast(res.message || "Saved.");
      return;
    }
    var shown = false;
    if (res.errors && $form) {
      $.each(res.errors, function (name, text) {
        var $input = $form.find('[name="' + name + '"], [name="' + name + '[]"]').first();
        if (!$input.length) { return; }
        var $group = $input.closest(".form-group");
        if (!$group.length) { $group = $input.parent(); }
        $group.addClass("has-error").append('<span class="error js-error">' + esc(text) + "</span>");
        if (!shown) { $input.trigger("focus"); shown = true; }
      });
    }
    if (res.message || !shown) {
      toast(res.message || (res.errors ? $.map(res.errors, function (t) { return t; }).join(" ") : "Something went wrong."), "error");
    }
  }

  $(document).on("submit", "form.js-form", function (e) {
    e.preventDefault();
    var form = this, $form = $(form);
    var go = function () {
      var $btn = $form.find('[type="submit"]');
      $btn.prop("disabled", true).data("label", $btn.html()).html('<i class="fas fa-spinner fa-spin"></i> ' + ($btn.data("busy") || "Processing"));
      send(form.action, new FormData(form), function (res) {
        $btn.prop("disabled", false).html($btn.data("label"));
        handle(res, $form);
      });
    };
    if ($form.data("confirm")) {
      confirmBox($form.data("confirm")).then(function (r) { if (r.value) { go(); } });
    } else {
      go();
    }
  });

  $(document).on("click", "[data-post]", function (e) {
    e.preventDefault();
    var $b = $(this);
    var post = function (extra) {
      var data = new FormData();
      if (extra !== undefined) { data.append($b.data("field") || "note", extra); }
      $.each($b[0].dataset, function (k, v) { if (k.indexOf("value") === 0 && k.length > 5) { data.append(k.charAt(5).toLowerCase() + k.slice(6), v); } });
      $b.prop("disabled", true);
      send($b.data("post"), data, function (res) { $b.prop("disabled", false); handle(res, null); });
    };
    if ($b.data("prompt")) {
      confirmBox($b.data("prompt"), { type: "question", input: "text", confirmButtonText: "Send" }).then(function (r) { if (r.value !== undefined && !r.dismiss) { post(r.value || ""); } });
    } else if ($b.data("confirm")) {
      confirmBox($b.data("confirm")).then(function (r) { if (r.value) { post(); } });
    } else {
      post();
    }
  });

  /* ---------- tabs: the hash opens one, a click keeps it in the address ---------- */
  $(document).on("shown.bs.tab", 'a[data-toggle="tab"]', function () {
    history.replaceState(null, "", $(this).attr("href"));
    window.dispatchEvent(new Event("resize")); // charts in a tab that was hidden
  });
  if (location.hash) { $('a[data-toggle="tab"][href="' + location.hash + '"]').tab("show"); }

  /* ---------- night mode (as the app's html.dark), kept in this browser ---------- */
  $(document).on("click", ".js-night", function (e) {
    e.preventDefault();
    var dark = !$("html").hasClass("dark");
    $("html").toggleClass("dark", dark).toggleClass("sidebar-light", !dark);
    $(this).find("i").attr("class", dark ? "fas fa-sun" : "fas fa-moon");
    try { localStorage.setItem("derz-admin-night", dark ? "1" : "0"); } catch (err) {}
    renderCharts();
  });

  /* ---------- charts: <canvas data-chart='{type, labels, datasets, money, stacked}'> ---------- */
  var charts = [];
  var palette = ["#038F5C", "#1a73e8", "#f4a100", "#7e57c2", "#e53935", "#00acc1", "#8d9e98", "#ef6c00"];
  function renderCharts() {
    if (!window.Chart) { return; }
    var dark = $("html").hasClass("dark");
    var ink = dark ? "#9aa8b5" : "#6c757d", line = dark ? "#2e3740" : "#ececec";
    charts.forEach(function (c) { c.destroy(); });
    charts = [];
    $("canvas[data-chart]").each(function () {
      var cfg = JSON.parse(this.getAttribute("data-chart"));
      var round = cfg.type === "doughnut";
      var ds = cfg.datasets.map(function (d, i) {
        var color = d.color || palette[i % palette.length];
        return $.extend({ backgroundColor: round ? palette : (cfg.type === "line" ? color + "22" : color), borderColor: round ? (dark ? "#1d2127" : "#fff") : color,
          borderWidth: 2, tension: 0.3, cubicInterpolationMode: "monotone", fill: cfg.type === "line" && i === 0, pointRadius: cfg.type === "line" ? 2 : 0, maxBarThickness: 34 }, d);
      });
      var fmt = function (v) { return cfg.money ? "ETB " + Number(v).toLocaleString() : Number(v).toLocaleString(); };
      charts.push(new Chart(this, {
        type: cfg.type, data: { labels: cfg.labels, datasets: ds },
        options: {
          responsive: true, maintainAspectRatio: false, interaction: { mode: "index", intersect: false },
          plugins: { legend: { display: ds.length > 1 || round, position: "bottom", labels: { color: ink, boxWidth: 12, font: { family: "Signika" } } },
            tooltip: { callbacks: { label: function (c) { return (c.dataset.label ? c.dataset.label + ": " : "") + fmt(c.parsed.y !== undefined ? c.parsed.y : c.parsed); } } } },
          scales: round ? {} : { x: { stacked: !!cfg.stacked, grid: { display: false }, ticks: { color: ink, font: { family: "Signika" } } },
            y: { stacked: !!cfg.stacked, beginAtZero: true, grid: { color: line }, ticks: { color: ink, precision: cfg.money ? undefined : 0, font: { family: "Signika" },
              callback: function (v) { return cfg.money && v >= 1000 ? (v / 1000) + "K" : v; } } } },
          cutout: round ? "65%" : undefined,
        },
      }));
    });
  }
  $(window).on("load", renderCharts);


  /* ---------- lists: the app's DataTables (export buttons, search, paging; cards on phones) ---------- */
  function exportBtn(extend, icon, title) {
    return { extend: extend, text: '<i class="' + icon + '"></i>', titleAttr: title, title: function () { return $.trim($(".page-title").first().text()) || "DerZ Admin"; }, footer: true, exportOptions: { columns: ":visible" } };
  }
  if ($.fn.DataTable) {
    $("table.table-export").each(function () {
      var $t = $(this), serverPaged = $t.is("[data-server-paged]");
      var lastHasButtons = $t.find("thead th").last().text().trim() === "";
      $t.DataTable({
        dom: '<"row"<"col-sm-6 mb-xs"B><"col-sm-6"f>><"table-responsive"t>' + (serverPaged ? "" : "p"),
        lengthChange: false,
        pageLength: 25,
        paging: !serverPaged,
        info: false,
        order: [],
        autoWidth: false,
        language: { emptyTable: $t.attr("data-card-empty") || "Nothing here yet.", zeroRecords: "Nothing matches." },
        columnDefs: lastHasButtons ? [{ targets: [-1], orderable: false, searchable: false }] : [],
        buttons: [
          exportBtn("copyHtml5", "far fa-copy", "Copy"),
          exportBtn("excelHtml5", "fa fa-file-excel", "Excel"),
          exportBtn("csvHtml5", "fa fa-file-alt", "CSV"),
          exportBtn("pdfHtml5", "fa fa-file-pdf", "PDF"),
          exportBtn("print", "fa fa-print", "Print"),
          { extend: "colvis", text: '<i class="fas fa-columns"></i>', titleAttr: "Columns", postfixButtons: ["colvisRestore"] }
        ]
      });
    });
  }

  /* ---------- small helpers ---------- */
  $(document).on("change", "[data-autosubmit]", function () { this.form.submit(); });
  var $flash = $("#flash");
  if ($flash.length) { toast($flash.data("message"), $flash.data("type") === "error" ? "error" : "success"); }
  // the app shows a loading ring until the page is ready
  $(window).on("load", function () { $("body").removeClass("loading-overlay-showing"); });
  window.derzSend = send;
  window.derzHandle = handle;
})(jQuery);
