$('i.spinner-icon').hide();

function updateItems(r) {
    _opts.items.available = r.available;
    _opts.items.assigned = r.assigned;
    search('available');
    search('assigned');
}

function updateUsers(r) {
    _opts.users = r;
    listUsers();
}

$('#list-users').on('click', 'a[data-target]', function () {
    var $this = $(this);
    var target = $this.data('target');
    var page = _opts.users[target];
    if (page !== undefined) {
        $.get(_opts.getUserUrl, {page: page}, function (r) {
            updateUsers(r);
        });
    }

    return false;
});

$('.btn-assign').click(function () {
    var $this = $(this);
    var target = $this.data('target');
    var items = $('select.list[data-target="' + target + '"]').val();
    var $spinner = $this.find('i.spinner-icon');

    if (items && items.length) {
        $spinner.show();
        $.post($this.attr('href'), { items: items }, function (r) {
            updateItems(r);
        }).always(function () {
            $spinner.hide();
        });
    }

    return false;
});


$('.search[data-target]').keyup(function () {
    search($(this).data('target'));
});

function search(target) {
    var $list = $('select.list[data-target="' + target + '"]');
    $list.html('');
    var q = $('.search[data-target="' + target + '"]').val();

    var groups = {
        role: [$('<optgroup label="Roles">'), false],
        permission: [$('<optgroup label="Permission">'), false],
        route: [$('<optgroup label="Routes">'), false],
    };
    $.each(_opts.items[target], function (name, group) {
        if (name.indexOf(q) >= 0) {
            $('<option>').text(name).val(name).appendTo(groups[group][0]);
            groups[group][1] = true;
        }
    });
    $.each(groups, function () {
        if (this[1]) {
            $list.append(this[0]);
        }
    });
}

function listUsers() {
    var $list = $('#list-users');
    var first = true;

    // Bangun elemen lewat DOM jQuery (bukan string innerHTML) supaya
    // username dari API tidak dieksekusi sebagai HTML (stored XSS).
    function addBadge($badge) {
        if (!first) {
            $list.append(' ');
        }
        first = false;
        $list.append($badge);
    }

    $list.empty();
    $.each(_opts.users.users, function (i, user) {
        addBadge($('<span>').addClass('badge bg-info').append(
            $('<a>').addClass('text-dark').attr('href', user.link).text(user.username)
        ));
    });

    $list.append('<br>');
    if (typeof _opts.users.prev !== 'undefined') {
        addBadge($('<span>').addClass('badge bg-primary').append(
            $('<a>').addClass('text-white').attr('href', '#')
                .attr('data-target', 'prev').html('&laquo;')
        ));
    }
    if (typeof _opts.users.next !== 'undefined') {
        addBadge($('<span>').addClass('badge bg-primary').append(
            $('<a>').addClass('text-white').attr('href', '#')
                .attr('data-target', 'next').html('&raquo;')
        ));
    }
}

// initial
search('available');
search('assigned');
listUsers();
