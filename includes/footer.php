<?php
// includes/footer.php
?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    // إعدادات توستر
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    });
    
    // حذف العنصر بتأكيد
    function confirmDelete(formId, message) {
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: message || 'سيتم الحذف نهائياً ولا يمكن التراجع عن ذلك',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById(formId).submit();
            }
        });
    }
    
    // عرض رسالة نجاح
    function showSuccess(message) {
        Toast.fire({
            icon: 'success',
            title: message
        });
    }
    
    // عرض رسالة خطأ
    function showError(message) {
        Toast.fire({
            icon: 'error',
            title: message
        });
    }
    </script>
</body>
</html>
<?php
// إغلاق اتصال قاعدة البيانات
$pdo = null;
?>