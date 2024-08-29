document.addEventListener('DOMContentLoaded', function() {
    
    document.querySelectorAll('.edit-room-button').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
    
            // Get room data from data attributes
            var roomId = this.getAttribute('data-room-id');
            var roomName = this.getAttribute('data-room-name');
            var roomDescription = this.getAttribute('data-room-description');
            var maxGuests = this.getAttribute('data-max-guests');
            var costPerDay = this.getAttribute('data-cost-per-day');
            var roomAmenities = JSON.parse(this.getAttribute('data-amenities')); // Parse the JSON string
    
            // Populate the modal fields with room data
            document.getElementById('edit-room-id').value = roomId;
            document.getElementById('edit_room_name').value = roomName;
            document.getElementById('edit_room_description').value = roomDescription;
            document.getElementById('edit_max_guests').value = maxGuests;
            document.getElementById('edit_cost_per_day').value = costPerDay;
    
            // Check the amenities that are selected for the room
            document.querySelectorAll('#edit-amenities-checkboxes input[type="checkbox"]').forEach(function(checkbox) {
                checkbox.checked = roomAmenities.includes(checkbox.value);
            });

            // Show the modal
            document.getElementById('edit-room-modal').style.display = 'block';
        });
    });


    document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            var menu = this.nextElementSibling;
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        });
    });

    document.addEventListener('click', function(event) {
        var isClickInsideDropdown = event.target.closest('.actions-dropdown');
        if (!isClickInsideDropdown) {
            document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                menu.style.display = 'none';
            });
        }
    });
    
});