// Sembunyikan semua ikon spinner
$('i.spinner-icon').hide();

function updateRoutes(r) {
    _opts.routes.available = r.available;
    _opts.routes.assigned = r.assigned;
    search('available');
    search('assigned');
}

// Tombol untuk menambahkan route baru
$('#btn-new').click(function () {
    var $this = $(this);
    var $spinner = $this.find('.spinner-icon');
    var route = $('#inp-route').val().trim();

    if (route !== '') {
        $spinner.show();
        $.post($this.attr('href'), {route: route}, function (r) {
            $('#inp-route').val('').focus();
            updateRoutes(r);
        }).always(function () {
            $spinner.hide();
        });
    }
    return false;
});

// Tombol untuk assign route
$('.btn-assign').click(function () {
    var $this = $(this);
    var $spinner = $this.find('.spinner-icon');
    var target = $this.data('target');
    var routes = $('select.list[data-target="' + target + '"]').val();

    if (routes && routes.length) {
        $spinner.show();
        $.post($this.attr('href'), {routes: routes}, function (r) {
            updateRoutes(r);
        }).always(function () {
            $spinner.hide();
        });
    }
    return false;
});

// Tombol refresh route
$('#btn-refresh').click(function () {
    var $icon = $(this).find('.spinner-icon');
    
    // Tampilkan dan mulai animasi
    $icon.removeClass('d-none').addClass('spin-animation');

    $.post($(this).attr('href'), function (r) {
        updateRoutes(r);
    }).always(function () {
        // Sembunyikan kembali setelah selesai
        $icon.removeClass('spin-animation').addClass('d-none');
    });

    return false;
});


$('.search[data-target]').keyup(function () {
    search($(this).data('target'));
});

function search(target) {
    var $list = $('select.list[data-target="' + target + '"]');
    $list.html('');
    var q = $('.search[data-target="' + target + '"]').val();
    $.each(_opts.routes[target], function () {
        var r = this;
        if (r.indexOf(q) >= 0) {
            $('<option>').text(r).val(r).appendTo($list);
        }
    });
}

// initial
search('available');
search('assigned');
