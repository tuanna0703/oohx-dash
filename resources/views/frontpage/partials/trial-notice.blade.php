{{--
    Thông báo website đang chạy thử nghiệm, chưa hoàn tất đăng ký với Bộ Công Thương.

    Yêu cầu review: hiện ở đầu trang, giữ 5-7s để khách tiếp nhận thông tin.

    Cố ý KHÔNG ghi nhớ trạng thái đã đóng (không cookie/localStorage): yêu cầu là
    mọi khách đều phải nhìn thấy, nên nó hiện lại ở mỗi lần tải trang. Tắt toàn
    site bằng OOHX_TRIAL_MODE=false khi đã có xác nhận đăng ký.
--}}
@if(config('policies.trial_mode'))
    <div class="trial-notice" id="trial-notice" role="status" aria-live="polite">
        <div class="w trial-notice-in">
            <span class="material-symbols-outlined trial-notice-ico" aria-hidden="true">warning</span>
            <span class="trial-notice-txt">
                Website đang hoạt động ở chế độ thử nghiệm, đang thực hiện đăng ký với Bộ Công Thương.
            </span>
            <button type="button" class="trial-notice-x" id="trial-notice-x" aria-label="Đóng thông báo">&times;</button>
        </div>
    </div>
    @push('scripts')
        <script>
            (function () {
                var el = document.getElementById('trial-notice');
                if (!el) return;

                var hide = function () {
                    el.classList.add('is-gone');
                    // Bỏ khỏi luồng đọc của trình đọc màn hình sau khi hiệu ứng chạy xong.
                    setTimeout(function () { el.hidden = true; }, 400);
                };

                requestAnimationFrame(function () { el.classList.add('is-in'); });
                var timer = setTimeout(hide, 6500); // giữa khoảng 5-7s theo yêu cầu

                document.getElementById('trial-notice-x').addEventListener('click', function () {
                    clearTimeout(timer);
                    hide();
                });
            })();
        </script>
    @endpush
@endif
