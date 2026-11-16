/* The DerZ app's DataTables phone cards: copied from the app (assets/js/app.fn.js) - keep in step */
var DT_MOBILE_QUERY = "(max-width: 767px)";
var dtMobileUid = 0;

// Table row classes and the card state they show as
var DT_MOBILE_ROW_STATES = {
  "derz-row-error": "is-error",
  danger: "is-danger",
  warning: "is-warning",
  "bp-below-cost": "is-warning",
  success: "is-success",
  info: "is-info",
  "text-muted": "is-muted",
  "text-weight-bold": "is-bold",
};

function dtMobileViewport() {
  return typeof window.matchMedia === "function" && window.matchMedia(DT_MOBILE_QUERY).matches;
}

function dtText(key, fallback) {
  return (window.derzText && derzText[key]) || fallback;
}

function initDtMobileCards(api) {
  if (!api || typeof api.table !== "function") {
    return;
  }

  var $table = $(api.table().node());
  if (!$table.hasClass("table-mobile-cards")) {
    return;
  }
  // Attached already: the page-load loop and the init.dt hook both call this
  var settings = api.settings()[0];
  if ($table.data("mobile-cards-settings") === settings) {
    return;
  }
  $table.data("mobile-cards-settings", settings);

  var $wrapper = $(api.table().container());
  $wrapper.addClass("dt-mobile-cards-enabled");
  // If an older outer .table-responsive wraps the DataTable, mark it too
  $wrapper.closest(".table-responsive").addClass("dt-mobile-cards-host");

  var $cards = $wrapper.children(".dt-mobile-cards");
  if (!$cards.length) {
    // no aria-live: every search keystroke would read the whole list aloud
    $cards = $('<div class="dt-mobile-cards"></div>');
    var $tableResponsive = $wrapper.children(".table-responsive");
    if ($tableResponsive.length) {
      $tableResponsive.after($cards);
    } else {
      $wrapper.append($cards);
    }
  }

  $table.data("mobile-cards-target", $cards);

  // Re-bind per api instance (server-side tables may destroy/re-init)
  api.off("draw.dt.mobileCards");
  api.on("draw.dt.mobileCards", function () {
    renderDtMobileCards(api, $table.data("mobile-cards-target"));
  });

  watchDtMobileTable($table);
  renderDtMobileCards(api, $cards);
}

/** What every card of the table needs: headers, visible columns and the data-card-* options. */
function buildDtMobileContext(api) {
  var $table = $(api.table().node());
  var ctx = { api: api, $table: $table, headers: [], visible: [], lastVisible: -1 };
  api.columns().indexes().each(function (i) {
    var column = api.column(i);
    ctx.headers[i] = $.trim($(column.header()).text());
    ctx.visible[i] = column.visible();
    if (ctx.visible[i]) {
      ctx.lastVisible = i;
    }
  });

  var num = function (attr, fallback) {
    var value = parseInt($table.attr(attr), 10);
    return isNaN(value) ? fallback : value;
  };
  ctx.titleCol = num("data-card-title-col", ctx.headers.length > 1 ? 1 : 0);
  ctx.imageCol = num("data-card-image-col", -1);
  ctx.subtitleCol = num("data-card-subtitle-col", -1);

  ctx.skipCols = {};
  String($table.attr("data-card-skip-cols") || "0")
    .split(",")
    .forEach(function (value) {
      var index = parseInt($.trim(value), 10);
      if (!isNaN(index)) {
        ctx.skipCols[index] = true;
      }
    });
  if (ctx.imageCol >= 0) {
    ctx.skipCols[ctx.imageCol] = true;
  }
  if (ctx.subtitleCol >= 0) {
    ctx.skipCols[ctx.subtitleCol] = true;
  }

  // Accordion cards: the key columns always show (in this order), the rest behind "More details".
  // data-card-key-cols="7,13" on the table, or data-card-key on a <th> (follows conditional columns).
  ctx.keyCols = [];
  String($table.attr("data-card-key-cols") || "")
    .split(",")
    .forEach(function (value) {
      var index = parseInt($.trim(value), 10);
      if (!isNaN(index) && ctx.keyCols.indexOf(index) === -1) {
        ctx.keyCols.push(index);
      }
    });
  ctx.headers.forEach(function (_, i) {
    if ($(api.column(i).header()).is("[data-card-key]") && ctx.keyCols.indexOf(i) === -1) {
      ctx.keyCols.push(i);
    }
  });
  ctx.accordion = ctx.keyCols.length > 0;

  ctx.iconClass = $table.attr("data-card-icon") || "fas fa-cube";
  ctx.emptyText = $table.attr("data-card-empty") || dtText("noInformation", "No information available");
  ctx.editable =
    String($table.attr("data-card-editable") || "") === "1" ||
    $table.hasClass("table-mobile-cards-editable");

  var actionColAttr = $table.attr("data-card-action-col");
  ctx.actionCol = -1;
  if (actionColAttr !== undefined && actionColAttr !== null && actionColAttr !== "") {
    ctx.actionCol = num("data-card-action-col", -1);
  } else if (ctx.lastVisible >= 0) {
    var probeRow = $(api.rows({ page: "current", search: "applied" }).nodes())
      .filter(function () {
        return !$(this).children("td[colspan]").length;
      })
      .first();
    if (probeRow.length) {
      var $probe = $(api.cell(probeRow[0], ctx.lastVisible).node());
      if (
        $probe.find(".btn, button, a.icon").length &&
        !$probe.find("input:not([type='hidden']), select, textarea").length
      ) {
        ctx.actionCol = ctx.lastVisible;
      }
    }
  }
  return ctx;
}

function renderDtMobileCards(api, $cards) {
  if (!$cards) {
    return;
  }
  var $table = $(api.table().node());

  // Restore required flags before rebuilding synced card fields
  $table.find("tbody input, tbody select, tbody textarea").each(function () {
    var $f = $(this);
    if ($f.data("was-required")) {
      $f.prop("required", true);
    }
  });

  $cards.empty();
  // Larger screens show the table: nothing to build (re-rendered when the breakpoint is crossed)
  if (!dtMobileViewport()) {
    $cards.prev(".dt-mobile-sort").remove();
    return;
  }

  var ctx = buildDtMobileContext(api);
  $cards.toggleClass("is-editable", ctx.editable);
  renderDtMobileSort(api, ctx, $cards);

  var rows = $(api.rows({ page: "current", search: "applied" }).nodes()).filter(function () {
    return !$(this).is("[data-card-skip-row]");
  });
  var dataRows = rows.filter(function () {
    return !$(this).children("td[colspan]").length;
  });
  var footerSummary = buildDtMobileFooterSummary(ctx);

  // Only wide rows left (a "no information" row): the empty state
  if (!dataRows.length) {
    $cards.append(
      '<div class="dt-mobile-card-empty"><i class="fas fa-inbox"></i><span>' +
        $("<div>").text(ctx.emptyText).html() +
        "</span></div>"
    );
  } else {
    rows.each(function () {
      $cards.append(buildDtMobileCard(ctx, this));
    });
  }

  appendDtMobileFooterSummary($cards, footerSummary, ctx);

  // Copied action buttons keep their tooltip attributes but need initialising
  if ($.fn.tooltip) {
    $cards.find('[data-toggle="tooltip"]').tooltip();
  }

  // The draw's own row changes (stripes, sorting classes) need no card refresh
  var observer = $table.data("mobile-cards-observer");
  if (observer) {
    observer.takeRecords();
  }
}

/** Phones have no column headers to tap: a "Sort by" list drives the table's ordering. */
function renderDtMobileSort(api, ctx, $cards) {
  var settings = api.settings()[0];
  var $sort = $cards.prev(".dt-mobile-sort");
  var options = [];
  if (settings.oFeatures.bSort) {
    ctx.headers.forEach(function (label, col) {
      if (!label || !ctx.visible[col] || col === ctx.actionCol || !settings.aoColumns[col].bSortable) {
        return;
      }
      options.push([col + ":asc", label + " ↑"], [col + ":desc", label + " ↓"]);
    });
  }
  if (!options.length) {
    $sort.remove();
    return;
  }
  if (!$sort.length) {
    $sort = $('<label class="dt-mobile-sort"><span></span><select class="form-control input-sm"></select></label>');
    $sort.find("select").on("change", function () {
      var parts = String($(this).val()).split(":");
      var $t = $(this).closest(".dataTables_wrapper").find("table.table-mobile-cards").first();
      if (parts.length === 2 && $t.length && $.fn.DataTable.isDataTable($t[0])) {
        $t.DataTable().order([[parseInt(parts[0], 10), parts[1]]]).draw();
      }
    });
    $cards.before($sort);
  }
  $sort.children("span").text(dtText("sortBy", "Sort by"));

  var order = api.order()[0] || [];
  var current = order.length ? order[0] + ":" + order[1] : "";
  var $select = $sort.find("select").empty();
  var known = options.some(function (o) {
    return o[0] === current;
  });
  if (!known) {
    $select.append($("<option></option>").val("").text("—"));
  }
  options.forEach(function (o) {
    $select.append($("<option></option>").val(o[0]).text(o[1]));
  });
  $select.val(known ? current : "");
}

/** The card (or note, for a row of one wide cell) of one table row. */
function buildDtMobileCard(ctx, rowNode) {
  var $tr = $(rowNode);
  var $card;
  if ($tr.children("td[colspan]").length) {
    $card = $('<div class="dt-mobile-card-note"></div>').html(
      $.trim(
        $tr
          .children("td")
          .map(function () {
            return $(this).html();
          })
          .get()
          .join(" ")
      )
    );
    linkDtMobileProxies($card, $tr);
  } else {
    $card = buildDtMobileRowCard(ctx, rowNode);
  }
  $card.data("dtRowNode", rowNode);
  $tr.data("dtMobileCard", $card[0]);
  return $card;
}

function buildDtMobileRowCard(ctx, rowNode) {
  var api = ctx.api;
  var $tr = $(rowNode);
  var rowIndex = api.row(rowNode).index();
  // cells by the table's own column number (hidden columns included)
  var cell = function (col) {
    return col >= 0 && col < ctx.headers.length ? $(api.cell(rowIndex, col).node()) : $();
  };

  var $titleCell = cell(ctx.titleCol);
  var titleText =
    $.trim($titleCell.clone().children().remove().end().text()) ||
    $.trim($titleCell.find("a, strong, b").first().text()) ||
    $.trim($titleCell.text()) ||
    dtText("cardItem", "Item");

  var $card = $('<article class="dt-mobile-card"></article>');
  $.each(DT_MOBILE_ROW_STATES, function (rowClass, state) {
    if ($tr.hasClass(rowClass)) {
      $card.addClass(state);
    }
  });
  var hasImage = ctx.imageCol >= 0;
  if (hasImage) {
    $card.addClass("has-thumb");
  }
  if (ctx.editable) {
    $card.addClass("is-editable");
  }

  var $top = $('<div class="dt-mobile-card-top"></div>');
  var $img = hasImage ? cell(ctx.imageCol).find("img").first() : $();

  if ($img.length) {
    var $thumb = $('<div class="dt-mobile-card-thumb"><img alt=""></div>');
    $thumb.find("img").attr({
      src: $img.attr("src"),
      alt: titleText,
    });
    $top.append($thumb);
  } else {
    $top.append(
      $('<div class="dt-mobile-card-thumb is-icon"></div>')
        .toggleClass("is-sm", !hasImage)
        .append($('<i aria-hidden="true"></i>').addClass(ctx.iconClass))
    );
  }

  var $meta = $('<div class="dt-mobile-card-top-meta"></div>');
  var $title = $('<strong class="dt-mobile-card-title" role="heading" aria-level="3"></strong>');
  // A link in the title cell (open the record) stays a link on the card
  var $link = $titleCell.find("a[href]").not("[data-toggle='dropdown']").first();
  if ($link.length) {
    $title.append(
      $('<a class="dt-mobile-card-title-link" data-dt-proxy="1"></a>')
        .attr("href", $link.attr("href"))
        .text(titleText)
        .data("dtProxyTarget", $link[0])
    );
  } else {
    $title.text(titleText);
  }
  $meta.append($title);

  if (ctx.subtitleCol >= 0) {
    var $subCell = cell(ctx.subtitleCol);
    var $highlightWrap = $('<div class="dt-mobile-card-highlights"></div>');
    // Preserve HTML badges/labels (e.g. payment status from server-side tables)
    if ($subCell.children().length) {
      var subHtml = $.trim($subCell.html() || "");
      if (subHtml) {
        var $sub = $('<span class="dt-mobile-card-highlight"></span>').html(subHtml);
        linkDtMobileProxies($sub, $subCell);
        $meta.append($highlightWrap.append($sub));
      }
    } else {
      var subtitleText = $.trim($subCell.text() || "");
      if (subtitleText) {
        $meta.append(
          $highlightWrap.append(
            $('<span class="dt-mobile-card-highlight"></span>').text(subtitleText)
          )
        );
      }
    }
  }

  var detailItems = [];
  for (var col = 0; col < ctx.headers.length; col++) {
    if (!ctx.visible[col] || col === ctx.titleCol || col === ctx.actionCol || ctx.skipCols[col]) {
      continue;
    }
    var $cell = cell(col);
    var valueText = $.trim($cell.text() || "");
    var hasControls = $cell.find("input, select, textarea, img, i, a, button").length > 0;
    if (!valueText && !hasControls) {
      continue;
    }
    detailItems.push({
      label: ctx.headers[col] || "",
      colIndex: col,
      $cell: $cell,
      valueText: valueText,
      // Any visible field becomes a synced (nameless) copy, even when the table
      // is not flagged editable — a plain HTML copy would keep its `name` and be
      // submitted twice, with the stale card value overriding the real one.
      editable: $cell.find("input:not([type='hidden']), select, textarea").length > 0,
    });
  }

  // Fallback highlight for image cards without explicit subtitle
  if (ctx.subtitleCol < 0 && hasImage && detailItems.length && !ctx.editable) {
    var first = detailItems.shift();
    $meta.append(
      $('<div class="dt-mobile-card-highlights"></div>').append(
        $('<span class="dt-mobile-card-highlight"></span>').text(first.valueText)
      )
    );
  }

  $top.append($meta);
  $card.append($top);

  // Why the row is marked (stock check, validation): shown, not only on hover
  var reason = $tr.attr("data-stock-reason") || ($tr.hasClass("derz-row-error") ? $tr.attr("title") : "");
  if (reason) {
    $card.append(
      $('<div class="dt-mobile-card-reason"></div>')
        .append('<i class="fas fa-exclamation-circle" aria-hidden="true"></i> ')
        .append($("<span></span>").text(reason))
    );
  }

  var parts = splitDtMobileDetails(ctx, detailItems);
  appendDtMobileDetails($card, parts.key, parts.more, !!$tr.data("dtMobileOpen"), function (item) {
    var $detail = $('<div class="dt-mobile-card-detail"></div>');
    // fields, nested tables and long text take the whole width
    if (item.editable || item.$cell.find("table").length || item.valueText.length > 48) {
      $detail.addClass("is-full");
    }
    if (item.label) {
      $detail.append($('<span class="dt-mobile-card-label"></span>').text(item.label));
    }
    if (item.editable) {
      $detail.append(buildDtMobileSyncedValue(item.$cell, rowIndex, item.colIndex));
    } else {
      var $value = $('<div class="dt-mobile-card-value"></div>').html($.trim(item.$cell.html() || ""));
      linkDtMobileProxies($value, item.$cell);
      $detail.append($value);
    }
    return $detail;
  });

  if (ctx.actionCol >= 0) {
    var $actionsCell = cell(ctx.actionCol);
    if ($actionsCell.length && $.trim($actionsCell.html() || "") !== "") {
      var $footer = $('<footer class="dt-mobile-card-actions"></footer>');
      $footer.html($actionsCell.html());
      linkDtMobileProxies($footer, $actionsCell);
      $card.append($footer);
    }
  }

  return $card;
}

/**
 * Accordion cards: the details of the key columns (in their order) and the rest.
 * A field stays among the key details: hidden behind "More details" a required
 * one would block the form ("An invalid form control is not focusable").
 */
function splitDtMobileDetails(ctx, items) {
  if (!ctx.accordion) {
    return { key: items, more: [] };
  }
  var key = [];
  ctx.keyCols.forEach(function (col) {
    items.forEach(function (item) {
      if (item.col === col || item.colIndex === col) {
        key.push(item);
      }
    });
  });
  var more = [];
  items.forEach(function (item) {
    if (key.indexOf(item) === -1) {
      (item.editable ? key : more).push(item);
    }
  });
  return { key: key, more: more };
}

/** The detail grid(s) of a card; with "more" details, a toggle and the collapsible grid after it. */
function appendDtMobileDetails($card, keyItems, moreItems, open, buildDetail) {
  var grid = function (items) {
    var $grid = $('<div class="dt-mobile-card-grid"></div>');
    items.forEach(function (item) {
      $grid.append(buildDetail(item));
    });
    return $grid;
  };
  if (keyItems.length) {
    $card.append(grid(keyItems));
  }
  if (!moreItems.length) {
    return;
  }
  var id = "dtm-more-" + ++dtMobileUid;
  $card.addClass("has-more");
  $card.append(
    $('<button type="button" class="dt-mobile-card-more"></button>')
      .attr({ "aria-controls": id, "data-count": moreItems.length })
      .append('<span class="dt-mobile-card-more-text"></span>')
      .append('<i class="fas fa-chevron-down" aria-hidden="true"></i>')
  );
  $card.append(grid(moreItems).addClass("dt-mobile-card-more-grid").attr("id", id));
  setDtMobileCardOpen($card, open);
}

function setDtMobileCardOpen($card, open) {
  var $btn = $card.children(".dt-mobile-card-more");
  var $grid = $card.children(".dt-mobile-card-more-grid");
  var focused = $btn[0] === document.activeElement;
  $card.toggleClass("is-open", !!open);
  // closed: the toggle above the hidden details; open: below them, to close from the bottom
  if (open) {
    $btn.insertAfter($grid);
  } else {
    $btn.insertBefore($grid);
  }
  if (focused) {
    $btn.trigger("focus");
  }
  $btn
    .attr("aria-expanded", open ? "true" : "false")
    .children(".dt-mobile-card-more-text")
    .text(open ? dtText("lessDetails", "Less details") : dtText("moreDetails", "More details") + " (" + $btn.attr("data-count") + ")");
}

// Open / close; remembered on the row (or the list, for the totals card) across redraws and refreshes
$(document)
  .off("click.dtMobileMore", ".dt-mobile-card-more")
  .on("click.dtMobileMore", ".dt-mobile-card-more", function () {
    var $card = $(this).closest(".dt-mobile-card");
    var open = !$card.hasClass("is-open");
    setDtMobileCardOpen($card, open);
    var row = $card.data("dtRowNode");
    if (row) {
      $(row).data("dtMobileOpen", open);
    } else if ($card.hasClass("is-summary")) {
      $card.closest(".dt-mobile-cards").data("dtMobileSummaryOpen", open);
    }
  });

/**
 * Card buttons are HTML copies of the table's buttons, so jQuery handlers bound
 * directly to the originals (e.g. $('.complete_order').on('click')) never fire on
 * them. Link each copy to its original; a tap on the copy is forwarded as a real
 * click on the original (still in the DOM inside the hidden table), so every kind
 * of wiring works: direct or delegated handlers, inline onclick, data-toggle
 * modals, form submit buttons, and handlers that read $(this).closest("tr").
 */
var DT_PROXY_SELECTOR =
  'a, button, input[type="submit"], input[type="button"], [onclick], [data-toggle]';

function linkDtMobileProxies($copy, $source) {
  // Copies must never be submitted with the form (the originals already are)
  $copy.find("input[name], select[name], textarea[name], button[name]").removeAttr("name");
  $copy.find("[id]").removeAttr("id");
  $copy.find("label[for]").removeAttr("for");

  var originals = $source.find(DT_PROXY_SELECTOR);
  $copy.find(DT_PROXY_SELECTOR).each(function (i) {
    var $el = $(this);
    // Batch toggles open the card's own panel; dropdown menus must open on the card
    if ($el.is(".toggle-batches, [data-toggle='dropdown']")) {
      return;
    }
    var original = originals.get(i);
    if (original) {
      $el.attr("data-dt-proxy", "1").data("dtProxyTarget", original);
    }
  });
}

document.addEventListener(
  "click",
  function (e) {
    var el = e.target && e.target.closest ? e.target.closest("[data-dt-proxy]") : null;
    if (!el || !el.closest(".dt-mobile-cards")) {
      return;
    }
    var original = window.jQuery ? $(el).data("dtProxyTarget") : null;
    if (!original || !document.body.contains(original)) {
      return; // fall back to the copy's own behaviour
    }
    e.preventDefault();
    e.stopPropagation();
    original.click();
  },
  true
);

/**
 * Two-way sync, table -> card: a page script that changes a row (a computed
 * margin, a red row, a value set by a "change all" button) rebuilds that row's
 * card. Watched: changes inside the rows / footer, and field change events.
 */
function watchDtMobileTable($table) {
  var previous = $table.data("mobile-cards-observer");
  if (previous) {
    previous.disconnect();
  }
  $table
    .off(".dtMobileWatch")
    .on("change.dtMobileWatch", "tbody input, tbody select, tbody textarea", function () {
      queueDtMobileRefresh($table, this);
    });
  if (typeof MutationObserver !== "function") {
    return;
  }
  var observer = new MutationObserver(function (records) {
    records.forEach(function (record) {
      queueDtMobileRefresh($table, record.target);
    });
  });
  observer.observe($table[0], {
    subtree: true,
    childList: true,
    characterData: true,
    attributes: true,
    attributeFilter: ["class", "title", "data-stock-reason", "src", "href", "disabled", "checked"],
  });
  $table.data("mobile-cards-observer", observer);
}

function queueDtMobileRefresh($table, node) {
  if (!$table.data("mobile-cards-target") || !dtMobileViewport()) {
    return;
  }
  var table = $table[0];
  var tbody = table.tBodies[0];
  var pending = $table.data("mobile-cards-pending");
  if (!pending) {
    pending = { rows: [], footer: false };
    $table.data("mobile-cards-pending", pending);
    setTimeout(function () {
      flushDtMobileRefresh($table);
    }, 0);
  }
  for (var n = node; n && n !== table; n = n.parentNode) {
    if (n.nodeName === "TR" && n.parentNode === tbody) {
      if (pending.rows.indexOf(n) === -1) {
        pending.rows.push(n);
      }
      return;
    }
    if (n === table.tFoot) {
      pending.footer = true;
      return;
    }
  }
}

function flushDtMobileRefresh($table) {
  var pending = $table.data("mobile-cards-pending");
  $table.removeData("mobile-cards-pending");
  var $cards = $table.data("mobile-cards-target");
  if (!pending || !$cards || !dtMobileViewport() || !$.fn.DataTable.isDataTable($table[0])) {
    return;
  }
  if (!pending.rows.length && !pending.footer) {
    return;
  }
  var ctx = buildDtMobileContext($table.DataTable());
  pending.rows.forEach(function (row) {
    var old = $(row).data("dtMobileCard");
    if (!old || !$.contains($cards[0], old)) {
      return; // not on the page shown
    }
    var kept = patchDtMobileCard($(old), buildDtMobileCard(ctx, row));
    $(row).data("dtMobileCard", kept[0]);
  });
  if (pending.footer) {
    $cards.children(".is-summary").remove();
    appendDtMobileFooterSummary($cards, buildDtMobileFooterSummary(ctx), ctx);
  }
  if ($.fn.tooltip) {
    $cards.find('[data-toggle="tooltip"]').tooltip();
  }
}

/** A field someone is typing in: rebuilding it would lose the caret (and the keyboard). */
function dtMobileIsTyping(el) {
  return !!el && ($(el).is("textarea") || ($(el).is("input") && !/^(checkbox|radio|button|submit|reset|file|hidden|image)$/i.test(el.type || "")));
}

/**
 * Put a rebuilt card in place of the old one. While the user types in a card field,
 * that field (and an open batch editor) stays in place and the rest is swapped.
 * Returns the card element left in the page.
 */
function patchDtMobileCard($old, $new) {
  var active = document.activeElement;
  var $panel = $old.children(".dt-mobile-batch-panel");
  var keeper = null;
  if (dtMobileIsTyping(active) && $.contains($old[0], active)) {
    $old.children().each(function () {
      if ($.contains(this, active)) {
        keeper = this;
      }
    });
  }
  var oldGrid = $old.children(".dt-mobile-card-grid")[0];
  var newGrid = $new.children(".dt-mobile-card-grid")[0];
  var keepPanel = !!keeper && keeper === $panel[0];
  var keepGrid = !!keeper && keeper === oldGrid && !!newGrid && oldGrid.children.length === newGrid.children.length;

  if (!keepPanel && !keepGrid) {
    // whole card: an open batch editor moves over, focus returns to the same control
    var focusAt = $.contains($old[0], active) ? $old.find(":input, a, button").index(active) : -1;
    if ($panel.length) {
      $new.append($panel);
      if ($panel.is(":visible")) {
        setBatchToggleIcon($new.find(".toggle-batches").first(), true);
      }
    }
    $old.replaceWith($new);
    if (focusAt >= 0) {
      $new.find(":input, a, button").eq(focusAt).trigger("focus");
    }
    return $new;
  }

  if (keepGrid) {
    var oldItems = $(oldGrid).children().toArray();
    var newItems = $(newGrid).children().toArray();
    oldItems.forEach(function (item, i) {
      if (!$.contains(item, active)) {
        $(item).replaceWith(newItems[i]);
      }
    });
  }
  $old.attr("class", $new.attr("class"));
  $old.children().each(function () {
    if (this !== keeper && this !== $panel[0]) {
      $(this).remove();
    }
  });
  var afterGrid = false;
  $new.children().toArray().forEach(function (child) {
    if (keepGrid && child === newGrid) {
      afterGrid = true;
      return;
    }
    if (keepPanel || !afterGrid) {
      $(child).insertBefore(keeper);
    } else if ($panel.length) {
      $(child).insertBefore($panel);
    } else {
      $old.append(child);
    }
  });
  if ($panel.is(":visible")) {
    setBatchToggleIcon($old.find(".toggle-batches").first(), true);
  }
  return $old;
}

/**
 * Editable cards move `required` between table and card fields based on the
 * viewport at render time. Re-render when the breakpoint is crossed (rotation,
 * resize) so required fields are never left hidden — a hidden required field
 * blocks form submission with "An invalid form control is not focusable".
 */
(function () {
  if (typeof window.matchMedia !== "function") {
    return;
  }
  var mq = window.matchMedia(DT_MOBILE_QUERY);
  var rerender = function () {
    if (!$.fn.DataTable) {
      return;
    }
    $("table.table-mobile-cards").each(function () {
      var $cards = $(this).data("mobile-cards-target");
      if ($cards && $.fn.DataTable.isDataTable(this)) {
        renderDtMobileCards($(this).DataTable(), $cards);
      }
    });
  };
  if (typeof mq.addEventListener === "function") {
    mq.addEventListener("change", rerender);
  } else if (typeof mq.addListener === "function") {
    mq.addListener(rerender);
  }
})();

/**
 * DataTables removes rows that are on other pages (or filtered out by search)
 * from the DOM, so a normal form submit only sent the visible page and any edits
 * on other pages were silently lost. Before submit, copy those rows' fields into
 * hidden inputs. Capture phase so it runs before AJAX submit handlers serialize.
 */
document.addEventListener(
  "submit",
  function (e) {
    var form = e.target;
    if (!form || form.tagName !== "FORM" || !window.jQuery || !$.fn.DataTable) {
      return;
    }
    var $form = $(form);
    $form.find("input.dt-offpage-field").remove();

    $form.find("table").each(function () {
      if (!$.fn.DataTable.isDataTable(this)) {
        return;
      }
      var api = $(this).DataTable();
      var info = api.page.info();
      if (info && info.serverSide) {
        return;
      }
      api
        .rows()
        .nodes()
        .toArray()
        .forEach(function (row) {
          if (document.body.contains(row)) {
            return;
          }
          $(row)
            .find("input[name], select[name], textarea[name]")
            .each(function () {
              if (this.disabled || this.type === "file" || this.type === "submit" || this.type === "button") {
                return;
              }
              if ((this.type === "checkbox" || this.type === "radio") && !this.checked) {
                return;
              }
              var name = this.name;
              var values = [];
              if (this.tagName === "SELECT" && this.multiple) {
                $(this)
                  .find("option:selected")
                  .each(function () {
                    values.push(this.value);
                  });
              } else {
                values.push($(this).val());
              }
              values.forEach(function (value) {
                $('<input type="hidden" class="dt-offpage-field">')
                  .attr("name", name)
                  .val(value == null ? "" : value)
                  .appendTo($form);
              });
            });
        });
    });
  },
  true
);

/**
 * Map table <tfoot> cells to labeled summary items for mobile cards.
 * Supports colspan label cells (e.g. "Total:") and empty spacer cells; the footer
 * cells left in the page are those of the visible columns, in order.
 */
function buildDtMobileFooterSummary(ctx) {
  var $footerRows = ctx.$table.children("tfoot").children("tr");
  if (!$footerRows.length) {
    return null;
  }
  var visibleCols = [];
  ctx.visible.forEach(function (isVisible, i) {
    if (isVisible) {
      visibleCols.push(i);
    }
  });

  var items = [];
  var title = "";

  $footerRows.each(function () {
    var pos = 0;
    $(this)
      .children("th, td")
      .each(function () {
        var $cell = $(this);
        var colspan = parseInt($cell.attr("colspan"), 10);
        if (isNaN(colspan) || colspan < 1) {
          colspan = 1;
        }
        var col = pos < visibleCols.length ? visibleCols[pos] : pos;

        var text = $.trim($cell.clone().children().remove().end().text() || $cell.text() || "");
        var html = $.trim($cell.html() || "");

        if (colspan > 1) {
          if (text) {
            title = text.replace(/[:\s]+$/, "") || title;
          }
          pos += colspan;
          return;
        }
        pos += 1;

        if (col === ctx.actionCol || ctx.skipCols[col]) {
          return;
        }
        if (text) {
          items.push({
            col: col,
            label: $.trim(ctx.headers[col] || "") || title || dtText("total", "Total"),
            html: html || $("<div>").text(text).html(),
          });
        }
      });
  });

  if (!items.length) {
    return null;
  }

  return {
    title: title || dtText("total", "Total"),
    items: items,
  };
}

function appendDtMobileFooterSummary($cards, summary, ctx) {
  if (!summary || !summary.items || !summary.items.length) {
    return;
  }
  // accordion tables: the key columns' totals show, the rest behind "More details"
  var parts = ctx ? splitDtMobileDetails(ctx, summary.items) : { key: summary.items, more: [] };

  var $card = $('<article class="dt-mobile-card is-summary"></article>');
  var $top = $('<div class="dt-mobile-card-top"></div>');
  $top.append(
    $('<div class="dt-mobile-card-thumb is-icon is-sm"></div>').append(
      $('<i class="fas fa-calculator" aria-hidden="true"></i>')
    )
  );
  $top.append(
    $('<div class="dt-mobile-card-top-meta"></div>').append(
      $('<strong class="dt-mobile-card-title" role="heading" aria-level="3"></strong>').text(summary.title)
    )
  );
  $card.append($top);

  appendDtMobileDetails($card, parts.key, parts.more, !!$cards.data("dtMobileSummaryOpen"), function (item) {
    var $detail = $('<div class="dt-mobile-card-detail"></div>');
    if (item.label) {
      $detail.append($('<span class="dt-mobile-card-label"></span>').text(item.label));
    }
    return $detail.append($('<div class="dt-mobile-card-value"></div>').html(item.html));
  });
  $cards.append($card);
}

function buildDtMobileSyncedValue($td, rowIndex, colIndex) {
  var $wrap = $('<div class="dt-mobile-card-value is-editable"></div>');
  // A plain copy: handlers bound to the table's fields must not run on the card's
  // (they would act with the wrong row); the sync below passes changes on instead.
  $wrap.append($td.contents().clone());
  // Batch editor stays on the source panel / mobile batch panel, not inline in the field;
  // a select2 widget copy would be dead, so the card shows the plain select
  $wrap.find(".stock-batch-panel, .select2-container").remove();
  $wrap
    .find("select.select2-hidden-accessible")
    .removeClass("select2-hidden-accessible")
    .removeAttr("aria-hidden tabindex data-select2-id");
  var $origFields = $td
    .find("input, select, textarea")
    .not(".stock-batch-panel :input, .select2-container :input");
  var mobileViewport = dtMobileViewport();

  $wrap.find("input, select, textarea").each(function (i) {
    var $orig = $origFields.eq(i);
    var $el = $(this);
    // its own id (labels pointing at the table's field now point at the card's)
    var oldId = $el.attr("id");
    if (oldId) {
      var newId = "dtm-" + ++dtMobileUid;
      $wrap
        .find("label")
        .filter(function () {
          return this.htmlFor === oldId;
        })
        .attr("for", newId);
      $el.attr("id", newId);
    }
    $el.removeAttr("name").removeAttr("required");
    $el.addClass("dt-mobile-field-sync");
    $el.attr({
      "data-sync-row": rowIndex,
      "data-sync-col": colIndex,
      "data-sync-idx": i,
    });
    if ($el.is(":checkbox, :radio")) {
      $el.prop("checked", $orig.prop("checked"));
    } else {
      $el.val($orig.val());
    }
    // On mobile the table is hidden — move required to the visible card field
    if (mobileViewport && ($orig.prop("required") || $orig.data("was-required"))) {
      $orig.data("was-required", true);
      $orig.prop("required", false);
      $el.prop("required", true);
    }
  });
  // no other copied ids (they would be duplicates of the table's)
  $wrap.find("[id]").not(".dt-mobile-field-sync").removeAttr("id");
  $wrap
    .find("label[for]")
    .filter(function () {
      return String(this.htmlFor).indexOf("dtm-") !== 0;
    })
    .removeAttr("for");

  return $wrap;
}

$(document)
  .off("input.dtMobileSync change.dtMobileSync", ".dt-mobile-field-sync")
  .on("input.dtMobileSync change.dtMobileSync", ".dt-mobile-field-sync", function (e) {
    var $el = $(this);
    var $wrapper = $el.closest(".dataTables_wrapper");
    var $table = $wrapper.find("table.table-mobile-cards").first();
    if (!$table.length || !$.fn.DataTable.isDataTable($table[0])) {
      return;
    }
    var api = $table.DataTable();
    var rowIdx = parseInt($el.attr("data-sync-row"), 10);
    var colIdx = parseInt($el.attr("data-sync-col"), 10);
    var syncIdx = parseInt($el.attr("data-sync-idx"), 10);
    if (!api.row(rowIdx).node()) {
      return;
    }
    var $orig = $(api.cell(rowIdx, colIdx).node())
      .find("input, select, textarea")
      .not(".stock-batch-panel :input, .select2-container :input")
      .eq(syncIdx);
    if (!$orig.length) {
      return;
    }
    if ($el.is(":checkbox, :radio")) {
      if (e.type !== "change") {
        return;
      }
      // radios: the table's group unchecks the others, the rebuilt card follows
      $orig.prop("checked", $el.prop("checked")).trigger("change");
    } else {
      $orig.val($el.val()).trigger("change"); // page scripts follow the table input
    }
  });

$(function () {
  $(".table-mobile-cards").each(function () {
    if ($.fn.DataTable && $.fn.DataTable.isDataTable(this)) {
      initDtMobileCards($(this).DataTable());
    }
  });
});

// Attach after any DataTable init (covers table-export in app.js)
$(document).on("init.dt", function (e, settings) {
  if (typeof initDtMobileCards !== "function" || !$.fn.dataTable) {
    return;
  }
  initDtMobileCards(new $.fn.dataTable.Api(settings));
});
