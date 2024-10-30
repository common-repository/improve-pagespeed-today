jQuery(document).ready(function ($) {
    $('#backup-btn').click(function (e) {
        e.preventDefault();
        $('#backup-dirs').toggle();
        return false;
    });
    $('#pagespeed_today_another_url').click(function (e) {
        e.preventDefault();
        $('input[name=pagespeed_today_url]').toggle();
        return false;
    });
    var table_optimizaion = $('#pagespeed_today_settings #post-table').DataTable({
        "columnDefs": [{
            "targets": 5,
            "orderable": false
        }]
    });
    var table_backup = $('#pagespeed_today_settings #backup-table').DataTable({
        "columnDefs": [{
            "targets": 3,
            "orderable": false
        }]
    });
    $('#pagespeed_today_settings #bulk-scan').click(function (e) {
        e.preventDefault();
        $('#pagespeed_today_settings .controls button').hide();
        $('#pagespeed_today_settings .controls .progress').show();
        $('#pagespeed_today_settings .controls .message').show();
        ajax = function (i, size) {
            if (i < size) {
                data = table_optimizaion.row(i).data();
                $.ajax({
                    type: "POST",
                    url: $(data[5]).attr('action'),
                    data: $(data[5]).serialize() + '&pagespeed_today_action=scan&pagespeed_today_ajax=1',
                    dataType: "json",
                    success: function (data) {
                        ajax(i, size);
                        $('#pagespeed_today_settings .controls .progress progress').val(Math.floor((i / size) * 100));
                    },
                    error: function () {
                        ajax(i, size);
                        $('#pagespeed_today_settings .controls .progress progress').val(Math.floor((i / size) * 100));
                    }
                });
                i++;
            } else {
                window.location.reload();
            }
        }
        ajax(0, table_optimizaion.rows().data().length);
        return false;
    })
    $('#pagespeed_today_settings #bulk-optimization').click(function (e) {
        e.preventDefault();
        $('#pagespeed_today_settings .controls button').hide();
        $('#pagespeed_today_settings .controls .progress').show();
        $('#pagespeed_today_settings .controls .message').show();
        ajax = function (i, size) {
            if (i < size) {
                data = table_optimizaion.row(i).data();
                $.ajax({
                    type: "POST",
                    url: $(data[5]).attr('action'),
                    data: $(data[5]).serialize() + '&pagespeed_today_action=process&pagespeed_today_ajax=1',
                    dataType: "json",
                    success: function (data) {
                        ajax(i, table_optimizaion.rows().data().length);
                        $('#pagespeed_today_settings .controls .progress progress').val(Math.floor((i / size) * 100));
                    },
                    error: function () {
                        ajax(i, table_optimizaion.rows().data().length);
                        $('#pagespeed_today_settings .controls .progress progress').val(Math.floor((i / size) * 100));
                    }
                });
                i++;
            } else {
                window.location.reload();
            }
        }
        ajax(0, table_optimizaion.rows().data().length);
        return false;
    })
    $('#pagespeed_today_settings #bulk-restore').click(function (e) {
        e.preventDefault();
        $('#pagespeed_today_settings .controls button').hide();
        $('#pagespeed_today_settings .controls .progress').show();
        $('#pagespeed_today_settings .controls .message').show();
        ajax = function (i, size) {
            if (i < size) {
                data = table_backup.row(i).data();
                $.ajax({
                    type: "POST",
                    url: $(data[3]).attr('action'),
                    data: $(data[3]).serialize() + '&pagespeed_today_action=restore_backup&pagespeed_today_ajax=1',
                    dataType: "json",
                    success: function (response) {
                        ajax(i, table_backup.rows().data().length);
                        $('#pagespeed_today_settings .controls .progress progress').val(Math.floor((i / size) * 100));
                    },
                    error: function (response) {
                        ajax(i, table_backup.rows().data().length);
                        $('#pagespeed_today_settings .controls .progress progress').val(Math.floor((i / size) * 100));
                    }
                });
                i++;
            } else {
                window.location.reload();
            }
        }
        ajax(0, table_backup.rows().data().length);
        return false;
    })
    
    $('button[name=pagespeed_today_action]').click(function(e){
        
        $('#pagespeed_today_settings .loading').show();
        
        return true;
    })
});