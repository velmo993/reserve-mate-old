document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.available-room-amenities li i').forEach(function(icon) {
        icon.addEventListener('click', function(e) {
            e.stopPropagation();
    
            // Toggle 'active' class on the clicked icon
            if (this.classList.contains('active')) {
                this.classList.remove('active');
            } else {
                // Remove 'active' class from all other icons
                document.querySelectorAll('.available-room-amenities li i').forEach(function(i) {
                    i.classList.remove('active');
                });
                this.classList.add('active');
            }
        });
    });
    
    // Optional: Hide tooltip when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.available-room-amenities li i')) {
            document.querySelectorAll('.available-room-amenities li i').forEach(function(icon) {
                icon.classList.remove('active');
            });
        }
    });

});
