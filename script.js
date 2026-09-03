/* ==========================================
   E-COMMERCE TEDDI - INTERACTIVE INTERFACE
   ========================================== */

document.addEventListener('DOMContentLoaded', () => {

  // 1. Konfirmasi Otomatis saat Menghapus atau Mengubah Data Penting
  const deleteButtons = document.querySelectorAll('.btn-danger, [data-confirm]');
  deleteButtons.forEach(button => {
    button.addEventListener('click', (e) => {
      const message = button.getAttribute('data-confirm') || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });

  // 2. Preview Gambar Upload secara Real-time (Untuk Admin Products)
  const imageInput = document.querySelector('input[type="file"][name="image"]');
  if (imageInput) {
    imageInput.addEventListener('change', function () {
      const file = this.files[0];
      if (file) {
        let preview = document.getElementById('image-preview');
        if (!preview) {
          preview = document.createElement('img');
          preview.id = 'image-preview';
          preview.style.maxWidth = '150px';
          preview.style.marginTop = '10px';
          preview.style.borderRadius = '8px';
          this.parentNode.appendChild(preview);
        }
        const reader = new FileReader();
        reader.onload = (e) => preview.src = e.target.result;
        reader.readAsDataURL(file);
      }
    });
  }

  // 3. Auto-hide Pesan Error / Notifikasi
  const alertBox = document.querySelector('p[style*="color:red"]');
  if (alertBox) {
    alertBox.style.padding = '10px 14px';
    alertBox.style.backgroundColor = '#fee2e2';
    alertBox.style.borderRadius = '6px';
    alertBox.style.marginBottom = '15px';
    
    setTimeout(() => {
      alertBox.style.transition = 'opacity 0.5s ease';
      alertBox.style.opacity = '0';
      setTimeout(() => alertBox.remove(), 500);
    }, 4000);
  }

});