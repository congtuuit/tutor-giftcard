(function($){
    $(document).ready(function(){
        // Claim form submit (if using AJAX)
        $('#tg-claim-form').on('submit', function(e){
            e.preventDefault();
            var code = $(this).find('input[name="tg_code"]').val();
            $('#tg-claim-result').text('Đang xử lý...');
            $.post(TG_Ajax.ajax_url, {
                action: 'tg_claim_code',
                tg_code: code,
                nonce: TG_Ajax.nonce
            }, function(resp){
                if (resp.success) {
                    $('#tg-claim-result').text(resp.data);
                    location.reload();
                } else {
                    $('#tg-claim-result').text(resp.data || resp);
                }
            });
        });

        // Redeem page app
        var $app = $('#tg-redeem-app');
        if ($app.length){
            var max = parseInt($app.data('max') || 1);
            var selected = [];

            function refreshSelected(){
                var $list = $('#tg-selected-list').empty();
                selected.forEach(function(id){
                    var $el = $('<div>').text('ID: ' + id);
                    $list.append($el);
                });
                $('#tg-confirm-redeem').prop('disabled', selected.length === 0);
            }

            $app.on('change', '.tg-course-checkbox', function(){
                var id = $(this).val();
                if ($(this).is(':checked')){
                    if (selected.length >= max){
                        alert('Bạn chỉ được chọn tối đa ' + max + ' khóa học.');
                        $(this).prop('checked', false);
                        return;
                    }
                    selected.push(id);
                } else {
                    selected = selected.filter(function(x){ return x != id; });
                }
                refreshSelected();
            });

            $('#tg-confirm-redeem').on('click', function(){
                var code = $app.data('code');
                if (!code) return alert('Missing code.');
                $(this).prop('disabled', true).text('Đang gửi...');
                // Call REST endpoint /tutor-giftcard/v1/redeem
                $.ajax({
                    url: TG_Ajax.rest_url + 'redeem',
                    method: 'POST',
                    beforeSend: function(xhr){
                        xhr.setRequestHeader('X-WP-Nonce', TG_Ajax.nonce);
                    },
                    data: {
                        code: code,
                        selected_ids: selected
                    },
                    success: function(resp){
                        if (resp.success){
                            $('#tg-redeem-result').text('Đổi thành công.');
                            location.reload();
                        } else {
                            $('#tg-redeem-result').text(resp.data || 'Lỗi khi đổi.');
                            $('#tg-confirm-redeem').prop('disabled', false).text('Xác nhận đổi');
                        }
                    },
                    error: function(){
                        $('#tg-redeem-result').text('Lỗi mạng.');
                        $('#tg-confirm-redeem').prop('disabled', false).text('Xác nhận đổi');
                    }
                });
            });
        }
    });
})(jQuery);
