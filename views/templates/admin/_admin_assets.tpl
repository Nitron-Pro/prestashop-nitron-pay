<div id="npToast" style="display:none; position:fixed; bottom:30px; left:50%; transform:translateX(-50%); background:#1f2937; color:#fff; padding:10px 22px; border-radius:20px; font-size:13px; box-shadow:0 6px 18px rgba(0,0,0,0.3); z-index:99999; border:1px solid #374151;">
    <i class="icon-check" style="color:#10b981; margin-left:6px;"></i> <span id="npToastText">کپی شد!</span>
</div>
<style>
    .np-copyable { cursor: pointer; display: inline-block; transition: opacity 0.15s ease; }
    .np-copyable:hover { opacity: 0.8; }
    .np-kpi { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:15px; }
    .np-kpi-box { flex:1; min-width:160px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px 14px; }
    .np-kpi-box b { display:block; font-size:18px; margin-top:4px; }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        function showToast(msg) {
            var toast = document.getElementById('npToast');
            var text = document.getElementById('npToastText');
            if (toast && text) {
                text.innerText = msg;
                toast.style.display = 'block';
                setTimeout(function () { toast.style.display = 'none'; }, 2200);
            }
        }
        document.querySelectorAll('.np-copyable').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                var textToCopy = this.getAttribute('data-clipboard-text') || this.innerText.trim();
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(textToCopy).then(function () { showToast('با موفقیت کپی شد'); });
                } else {
                    var textarea = document.createElement('textarea');
                    textarea.value = textToCopy;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    showToast('با موفقیت کپی شد');
                }
            });
        });
    });
</script>
