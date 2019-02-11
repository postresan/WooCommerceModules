/**
 * Created by reza on 4/11/16.
 */

jQuery(function(){
    jQuery('#billing_frotel_radio_').click(function(e){
        e.preventDefault();
        var t=jQuery(this);
        if (t.hasClass('disabled'))
            return false;
        var c=jQuery('#billing_frotel_coupon');
        c.css({
            backgroundColor:'inherit'
        });

        if (c.val().length == 0) {
            c.css({backgroundColor:'rgba(255,0,0,.2)'});
            alert('کد کوپن را وارد کنید.');
            return false;
        }
        t.addClass('disabled');
        jQuery.ajax({
            url:wc_ajax_url_frotel,
            dataType:'json',
            type:'post',
            data:'action=check_coupon&coupon='+c.val(),
            success:function(d){
                var h='';
                if (d.error == undefined || d.error == 1) {
                    c.css({backgroundColor:'rgba(255,0,0,.2)'});
                    h='<div class="alert alert-danger frotel_coupon_result"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button><div>'+(d.error==undefined?'در اتصال به سرور خطایی رخ داده است.':d.message)+'</div></div>';
                } else {
                    c.css({backgroundColor:'rgba(0,255,0,.2)'});
                    h='<div class="alert alert-success frotel_coupon_result"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button><div>'+d.message+'</div></div>';
                }
                c.parents('form').prepend(h);
                jQuery('.frotel_coupon_result').goTo();
            },
            complete:function(){
                t.removeClass('disabled');
            }
        });
        return false;
    });
});

(function($) {
    $.fn.goTo = function() {
        $('html, body').animate({
            scrollTop: $(this).offset().top + 'px'
        }, 'fast');
        return this;
    }
})(jQuery);
