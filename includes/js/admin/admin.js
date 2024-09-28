document.addEventListener('DOMContentLoaded', function() {
    let editRoomButtons = document.querySelectorAll('.edit-room-button');
    let tabButtons = document.querySelectorAll('.tab-button');
    let tabContents = document.querySelectorAll('.tab-content');
    let addRoomTab = document.getElementById('add-room-tab');
    let toggleButtons = document.querySelectorAll('.toggle-details');
    
    if (window.location.search.includes('page=manage-rooms')) {
        const activeTab = localStorage.getItem('activeTab');
        if (activeTab) {
            document.querySelectorAll('.tab-button, .tab-content').forEach(function(elem) {
                elem.classList.remove('active');
            });
            document.querySelector(`.tab-button[data-target="${activeTab}"]`).classList.add('active');
            document.querySelector(activeTab).classList.add('active');
        }
    
        document.querySelectorAll('.tab-button').forEach(function(button) {
            button.addEventListener('click', function() {
                document.querySelectorAll('.tab-button, .tab-content').forEach(function(elem) {
                    elem.classList.remove('active');
                });
    
                this.classList.add('active');
                const target = this.getAttribute('data-target');
                document.querySelector(target).classList.add('active');
    
                localStorage.setItem('activeTab', target);
            });
        });
    }
    
    function setupToggleDetails(toggleClass, dataIdPrefix) {
        const toggleButtons = document.querySelectorAll(toggleClass);
    
        if (toggleButtons) {
            toggleButtons.forEach(button => {
                const toggleDetails = function(event) {
                    if (event.type === 'touchstart') {
                        event.preventDefault();
                    }
    
                    const entityId = this.getAttribute(`data-${dataIdPrefix}-id`);
                    const detailsRow = document.getElementById('details-' + entityId);
                    const isVisible = detailsRow.style.display === 'table-row';
    
                    detailsRow.style.display = isVisible ? 'none' : 'table-row';
                    this.innerHTML = isVisible ? '<i class="fa fa-arrow-down" aria-hidden="true"></i>' : '<i class="fa fa-arrow-up" aria-hidden="true"></i>';
                };
    
                button.addEventListener('click', toggleDetails);
                button.addEventListener('touchstart', toggleDetails);
            });
        }
    }
    
    setupToggleDetails('.toggle-details-room', 'room');
    
    setupToggleDetails('.toggle-details-booking', 'booking');
    
    
    
    if(addRoomTab) {
        let dropArea = document.getElementById('drop-area');
        let fileInput = document.getElementById('room_images');
        let preview = document.getElementById('image-preview');
        let fileSelector = document.getElementById('file-selector');
    
        dropArea.addEventListener('click', function() {
            fileInput.click();
        });

        fileInput.addEventListener('change', function() {
            updatePreview(fileInput.files, preview, fileInput);
        });

        dropArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.add('dragging');
        });

        dropArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.remove('dragging');
        });

        dropArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.remove('dragging');

            let files = e.dataTransfer.files;
            fileInput.files = files;
            updatePreview(files, preview, fileInput);
        });
    }

    function updatePreview(files, previewElement, fileInput) {
        previewElement.innerHTML = '';
        
        Array.from(files).forEach((file, index) => {
            let img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.style.width = "100px";
            img.style.height = "100px";
            img.style.objectFit = "cover";
            img.style.position = "relative";
            
            let imageContainer = document.createElement('div');
            imageContainer.style.position = "relative";
            imageContainer.style.display = "inline-block";
            imageContainer.style.margin = "5px";
            
            let removeBtn = document.createElement('span');
            removeBtn.innerHTML = '&times;';
            removeBtn.style.position = 'absolute';
            removeBtn.style.top = '5px';
            removeBtn.style.right = '5px';
            removeBtn.style.cursor = 'pointer';
            removeBtn.style.backgroundColor = 'red';
            removeBtn.style.color = 'white';
            removeBtn.style.borderRadius = '50%';
            removeBtn.style.padding = '2px 5px';
            
            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                imageContainer.remove();
                removeFile(index, fileInput);
            });

            imageContainer.appendChild(img);
            imageContainer.appendChild(removeBtn);
            previewElement.appendChild(imageContainer);
        });
    }

    function removeFile(index, fileInput) {
        let dt = new DataTransfer();
        
        Array.from(fileInput.files).forEach((file, i) => {
            if (i !== index) {
                dt.items.add(file);
            }
        });

        fileInput.files = dt.files;
    }
    
    function updateRoomImages(roomId) {
        var imagesContainer = document.getElementById('existing-images-container');
        fetch('/wp-admin/admin-ajax.php?action=get_room_images&room_id=' + roomId)
        .then(response => response.json())
        .then(data => {
            imagesContainer.innerHTML = '';
            
            if (data && Array.isArray(data.data.images) && data.data.images.length > 0) {
                data.data.images.forEach(image => {
                    var imageItem = document.createElement('div');
                    imageItem.classList.add('image-item');
                    imageItem.innerHTML = `
                        <img src="${image.url}" width="100" height="auto">
                        <span class="remove-image" data-image-id="${image.id}">&times;</span>
                    `;
                    imagesContainer.appendChild(imageItem);
                });
                initializeRemoveImageButtons(roomId);
            } else {
                imagesContainer.innerHTML = '<p>No images found for this room.</p>';
            }
        })
        .catch(error => {
            if(error) {
                imagesContainer.innerHTML = '<p>Error loading images. Please try again later.</p>';
            }
        });
    }
    
    function initializeRemoveImageButtons(roomId) {
        let removeImageButtons = document.querySelectorAll('.remove-image');
        if (removeImageButtons.length > 0) {
            removeImageButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    var imageId = this.getAttribute('data-image-id');
                    var imageItem = this.closest('.image-item');
                    if (imageItem) {
                        imageItem.remove();
                    }
                   
                    fetch('/wp-admin/admin-ajax.php?action=delete_room_image', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ image_id: imageId }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateRoomImages(roomId);
                        }
                    })
                    .catch(error => {
                        // console.error('Error:', error);
                    });
                });
            });
        }
    }
    
    tabButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            tabButtons.forEach(function(btn) { btn.classList.remove('active'); });
            tabContents.forEach(function(content) { content.classList.remove('active'); });

            button.classList.add('active');

            var target = document.querySelector(button.getAttribute('data-target'));
            target.classList.add('active');
        });
    });
    
    if (editRoomButtons.length > 0) {
        editRoomButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                let roomId = this.getAttribute('data-room-id');
                let roomName = this.getAttribute('data-room-name');
                let roomDescription = this.getAttribute('data-room-description');
                let maxGuests = this.getAttribute('data-max-guests');
                let costPerDay = this.getAttribute('data-cost-per-day');
                let roomSize = this.getAttribute('data-room-size');
                let roomAmenities = JSON.parse(this.getAttribute('data-amenities'));
                
                document.getElementById('edit-room-id').value = roomId;
                document.getElementById('edit_room_name').value = roomName;
                document.getElementById('edit_room_description').value = roomDescription;
                document.getElementById('edit_max_guests').value = maxGuests;
                document.getElementById('edit_cost_per_day').value = costPerDay;
                document.getElementById('edit_room_size').value = roomSize;
        
                fetchAmenities(roomId, roomAmenities);
                
                updateRoomImages(roomId);
                
                openModal();
                
                document.querySelector('.close-button').addEventListener('click', function() {
                    closeModal();
                });
                document.querySelector('.modal-overlay').addEventListener('click', function() {
                    closeModal();
                });
            });
        });
        
        document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                let menu = this.nextElementSibling;
                menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
            });
        });
    
        document.addEventListener('click', function(event) {
            let isClickInsideDropdown = event.target.closest('.actions-dropdown');
            if (!isClickInsideDropdown) {
                document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                    menu.style.display = 'none';
                });
            }
        });
    }
    
    function fetchAmenities(roomId, roomAmenities) {
        fetch('/wp-admin/admin-ajax.php?action=fetch_amenities&room_id=' + roomId)
        .then(response => response.json())
        .then(data => {
            let amenitiesContainer = document.getElementById('edit_amenities_checkboxes');
            amenitiesContainer.innerHTML = '';
    
            data.amenities.forEach(function(amenity) {
                let checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'amenities[]';
                checkbox.value = amenity.id;
    
                checkbox.checked = amenity.selected;
    
                let label = document.createElement('label');
                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(' ' + amenity.name));
    
                amenitiesContainer.appendChild(label);
                amenitiesContainer.appendChild(document.createElement('br'));
            });
        })
        .catch(error => {
            // console.error('Error fetching amenities:', error);
        });
    }
    
    function openModal() {
        document.querySelector('.modal-overlay').style.display = 'block';
        document.getElementById('edit-room-modal').style.display = 'block';
    }
    
    function closeModal() {
        document.querySelector('.modal-overlay').style.display = 'none';
        document.getElementById('edit-room-modal').style.display = 'none';
    }
    
    
});