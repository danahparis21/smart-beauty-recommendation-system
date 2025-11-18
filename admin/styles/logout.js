document.addEventListener('DOMContentLoaded', function () {
  const logoutBtn = document.getElementById('logoutBtn');

  logoutBtn.addEventListener('click', function (e) {
    e.preventDefault();

    Swal.fire({
      html: `
        <div style="display: flex; flex-direction: column; align-items: center; gap: 15px;">
          <img src="../images/mainlogo.png" alt="Blessence Logo" style="width: 80px; height: auto; border-radius: 12px;">
          <h2 style="color: #e497aa; margin: 0;">Logout Confirmation</h2>
          <p style="color: #6c757d; text-align: center; max-width: 250px;">
            Are you sure you want to logout from the admin dashboard?
          </p>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'Yes, Logout',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#e497aa', // primary pink
      cancelButtonColor: '#6c757d', // muted gray
      reverseButtons: true,
      background: '#ffffff',
      backdrop: true,
      customClass: {
        popup: 'soft-popup'
      }
    }).then((result) => {
      if (result.isConfirmed) {
        // Redirect to logout page
        window.location.href = '../../index.php';
      }
    });
  });
});
