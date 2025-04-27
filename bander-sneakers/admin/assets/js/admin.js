/**
 * Bander-Sneakers - Admin JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const adminSidebar = document.querySelector('.admin-sidebar');
    const adminMain = document.querySelector('.admin-main');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');

            if (document.body.classList.contains('sidebar-collapsed')) {
                adminSidebar.style.width = '70px';
                adminMain.style.marginLeft = '70px';

                // Hide text in sidebar
                const sidebarTexts = document.querySelectorAll('.sidebar-header h1, .sidebar-header span, .sidebar-nav ul li a span, .sidebar-footer a span');
                sidebarTexts.forEach(text => {
                    text.style.display = 'none';
                });

                // Center icons
                const sidebarLinks = document.querySelectorAll('.sidebar-nav ul li a, .sidebar-footer a');
                sidebarLinks.forEach(link => {
                    link.style.justifyContent = 'center';
                    link.querySelector('i').style.marginRight = '0';
                });
            } else {
                adminSidebar.style.width = '250px';
                adminMain.style.marginLeft = '250px';

                // Show text in sidebar
                const sidebarTexts = document.querySelectorAll('.sidebar-header h1, .sidebar-header span, .sidebar-nav ul li a span, .sidebar-footer a span');
                sidebarTexts.forEach(text => {
                    text.style.display = 'inline';
                });

                // Left align icons
                const sidebarLinks = document.querySelectorAll('.sidebar-nav ul li a, .sidebar-footer a');
                sidebarLinks.forEach(link => {
                    link.style.justifyContent = 'flex-start';
                    link.querySelector('i').style.marginRight = '0.75rem';
                });
            }
        });
    }

    // Confirm delete
    const deleteButtons = document.querySelectorAll('.delete-btn');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
                e.preventDefault();
            }
        });
    });

    // Init datepickers
    const datepickers = document.querySelectorAll('.datepicker');

    if (datepickers.length > 0 && typeof flatpickr !== 'undefined') {
        datepickers.forEach(datepicker => {
            flatpickr(datepicker, {
                dateFormat: 'd/m/Y',
                locale: 'fr'
            });
        });
    }

    // Init select2
    const select2Inputs = document.querySelectorAll('.select2');

    if (select2Inputs.length > 0 && typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
        select2Inputs.forEach(select => {
            jQuery(select).select2({
                placeholder: select.getAttribute('placeholder') || 'Sélectionnez une option'
            });
        });
    }

    // Image preview on file input change
    const imageInputs = document.querySelectorAll('.image-input');

    imageInputs.forEach(input => {
        const preview = document.querySelector(input.getAttribute('data-preview'));

        if (preview) {
            input.addEventListener('change', function() {
                const file = this.files[0];

                if (file) {
                    const reader = new FileReader();

                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };

                    reader.readAsDataURL(file);
                }
            });
        }
    });

    // Toggle password visibility
    const passwordToggles = document.querySelectorAll('.password-toggle');

    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = document.querySelector(this.getAttribute('data-input'));

            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    this.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    input.type = 'password';
                    this.innerHTML = '<i class="fas fa-eye"></i>';
                }
            }
        });
    });
});
