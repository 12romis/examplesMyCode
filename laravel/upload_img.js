//open modal for edit img
var global_img_id;
$(document).on('click', '.select-image', function(e){
    e.preventDefault();
    var width = $(window).width()*0.9;
    var height = $(window).height()*0.9;
    global_img_id = $(this);
    cw.openIframe('/admin/open_edit_img', width, height, {padding: 0, margin: 0});
});

var height = $(window).height()*0.75;
$('#img_content').css('height', height);

$(document).on('click', '#img_modal img', function(){
    window.parent.global_img_id.closest('div.row').find('input').val($(this).attr('src'));
    var html = '<img src="'+$(this).attr('src')+'" width="100%">';
    window.parent.global_img_id.closest('div').next().html(html);
    window.parent.cw.close();
});


//upload img
$('#upload_img').on('submit', function(e) {
    e.preventDefault();
    var $that = $(this),
    formData = new FormData($that.get(0));
    $('#user_file').val('');
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
        }
    });

    $.ajax
    ({
        url: $that.attr('action'),
        type: $that.attr('method'),
        contentType: false, // ????? - ??????? ?????????????? ?????? ?? ?????????
        processData: false, // ????? - ??????? ?????????????? ????? ?? ?????????
        data: formData,
        dataType: 'json',
        success: function(data)
        {
            var adding = '<li>'+
                '<img src="'+data+'" alt="" style="max-height: 100%;">'+
                '</li>';
            $('#img_content ul').before(adding);
        }
    });
});
//upload image end

$('.modal_close').on('click', function(){
    window.parent.cw.close();
});