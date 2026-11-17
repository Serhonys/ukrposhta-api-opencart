(function ($) {
  'use strict';
  if (!$) return;
  var cfg = window.UKRPOSHTA_CFG || {};
  var selectedOffice = cfg.selected && cfg.selected.office_id ? String(cfg.selected.office_id) : '';

  function code() {
    return $('input[name="shipping_method"]:checked').val() || '';
  }
  function isUp() { return code().indexOf('ukrposhta.') === 0; }
  function isDoor() { return code().indexOf('_w2d') !== -1; }

  function payload() {
    var $opt = $('#ukrposhta-office option:selected');
    return {
      method_code: code(),
      city_id: $('#ukrposhta-city-id').val(),
      city_name: $('#ukrposhta-city').val(),
      region_name: $('#ukrposhta-region').val(),
      postcode: isDoor() ? $('#ukrposhta-postcode').val() : ($opt.data('postcode') || $('#ukrposhta-postcode').val() || ''),
      office_id: $opt.val() || '',
      office_name: $opt.data('name') || '',
      street: $('#ukrposhta-street').val(),
      house: $('#ukrposhta-house').val(),
      apartment: $('#ukrposhta-apartment').val()
    };
  }

  function save(sync) {
    if (!isUp() || !cfg.saveUrl) return;
    $.ajax({ url: cfg.saveUrl, type: 'post', data: payload(), async: !sync });
  }

  function recalc() {
    if (!cfg.recalcUrl) return;
    var postcode = payload().postcode;
    if (!postcode) return;
    $.getJSON(cfg.recalcUrl, { postcode: postcode }, function (json) {
      var quotes = (json && json.quotes) || {};
      $('input[name="shipping_method"]').each(function () {
        var value = $(this).val() || '';
        var key = value.indexOf('ukrposhta.') === 0 ? value.split('.')[1] : '';
        if (!key || !quotes[key]) return;
        var $label = $(this).closest('label');
        if (!$label.length) $label = $(this).parent();
        $label.contents().filter(function () { return this.nodeType === 3; }).remove();
        $label.append(document.createTextNode(' ' + quotes[key].title + ' — ' + quotes[key].text));
      });
    });
  }

  function toggle() {
    if (!isUp()) {
      $('#ukrposhta-fields').hide();
      return;
    }
    $('#ukrposhta-fields').show();
    if (isDoor()) {
      $('#ukrposhta-office-wrap').hide();
      $('#ukrposhta-address-wrap').show();
    } else {
      $('#ukrposhta-office-wrap').show();
      $('#ukrposhta-address-wrap').hide();
    }
  }

  function loadOffices(cityId) {
    if (!cityId || !cfg.officesUrl) return;
    $.getJSON(cfg.officesUrl, { city_id: cityId }, function (rows) {
      var $sel = $('#ukrposhta-office').empty();
      $sel.append($('<option>').val('').text(cfg.selectOffice || '—'));
      (rows || []).forEach(function (row) {
        var $opt = $('<option>').val(row.office_id).text(row.label).attr('data-postcode', row.postcode).attr('data-name', row.name);
        if (selectedOffice && selectedOffice === String(row.office_id)) $opt.prop('selected', true);
        $sel.append($opt);
      });
      save(false);
      recalc();
    });
  }

  var timer = null;
  $(document).off('.ukrposhta');
  $(document).on('change.ukrposhta', 'input[name="shipping_method"]', toggle);
  $(document).on('input.ukrposhta', '#ukrposhta-city', function () {
    var term = $(this).val();
    clearTimeout(timer);
    if (!cfg.citiesUrl || term.length < 2) {
      $('#ukrposhta-city-suggest').hide().empty();
      return;
    }
    timer = setTimeout(function () {
      $.getJSON(cfg.citiesUrl, { term: term }, function (rows) {
        var $box = $('#ukrposhta-city-suggest').empty();
        (rows || []).slice(0, 12).forEach(function (row) {
          $box.append($('<a href="#" class="list-group-item"></a>').text(row.label).data(row));
        });
        $box.toggle(!!(rows && rows.length));
      });
    }, 300);
  });
  $(document).on('click.ukrposhta', '#ukrposhta-city-suggest a', function (e) {
    e.preventDefault();
    var row = $(this).data();
    $('#ukrposhta-city').val(row.city_name);
    $('#ukrposhta-city-id').val(row.city_id);
    $('#ukrposhta-region').val(row.region_name);
    $('#ukrposhta-city-suggest').hide().empty();
    loadOffices(row.city_id);
  });
  var streetTimer = null;
  $(document).on('input.ukrposhta', '#ukrposhta-street', function () {
    var term = $(this).val();
    var cityId = $('#ukrposhta-city-id').val();
    clearTimeout(streetTimer);
    if (!cfg.streetsUrl || !cityId || term.length < 2) {
      $('#ukrposhta-street-suggest').hide().empty();
      return;
    }
    streetTimer = setTimeout(function () {
      $.getJSON(cfg.streetsUrl, { city_id: cityId, term: term }, function (rows) {
        var $box = $('#ukrposhta-street-suggest').empty();
        (rows || []).slice(0, 12).forEach(function (row) {
          $box.append($('<a href="#" class="list-group-item"></a>').text(row.label).data(row));
        });
        $box.toggle(!!(rows && rows.length));
      });
    }, 300);
  });
  $(document).on('click.ukrposhta', '#ukrposhta-street-suggest a', function (e) {
    e.preventDefault();
    $('#ukrposhta-street').val($(this).data('name') || $(this).text());
    $('#ukrposhta-street-suggest').hide().empty();
    save(false);
  });
  $(document).on('change.ukrposhta input.ukrposhta', '#ukrposhta-office, #ukrposhta-postcode, #ukrposhta-house, #ukrposhta-apartment', function () {
    if (this.id === 'ukrposhta-office') {
      var postcode = $('#ukrposhta-office option:selected').data('postcode');
      if (postcode) $('#ukrposhta-postcode').val(postcode);
    }
    save(false);
    recalc();
  });

  if (window.__ukrposhtaCapture) {
    document.removeEventListener('click', window.__ukrposhtaCapture, true);
  }
  window.__ukrposhtaCapture = function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('#button-shipping-method') : null;
    if (!btn || !isUp()) return;
    var data = payload();
    var bad = '';
    if (!data.city_name) bad = cfg.errorCity;
    else if (isDoor() && (!data.postcode || !data.street || !data.house)) bad = cfg.errorDoor;
    else if (!isDoor() && (!data.office_id || !data.postcode)) bad = cfg.errorOffice;
    if (bad) {
      e.preventDefault();
      e.stopPropagation();
      alert(bad);
      return;
    }
    save(true);
  };
  document.addEventListener('click', window.__ukrposhtaCapture, true);

  toggle();
  if ($('#ukrposhta-city-id').val()) loadOffices($('#ukrposhta-city-id').val());
})(window.jQuery);
