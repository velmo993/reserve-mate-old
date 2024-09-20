document.addEventListener('DOMContentLoaded', function () {
    let currentIndex = 0;
    let totalRooms = 0;
    let roomsData = [];
    let touchstartX = 0;
    let touchstartY = 0;
    let touchendX = 0;
    let touchendY = 0;
    var rooms = document.querySelectorAll('.single-room-container .available-room');
    var prevButton = document.getElementById('prev-room');
    var nextButton = document.getElementById('next-room');
        
    function amenitiesClickEvent() {
        document.querySelectorAll('.available-room-amenities li i').forEach(function(icon) {
            icon.addEventListener('click', function(e) {
                e.stopPropagation();
        
                if (this.classList.contains('active')) {
                    this.classList.remove('active');
                } else {
                    document.querySelectorAll('.available-room-amenities li i').forEach(function(i) {
                        i.classList.remove('active');
                    });
                    this.classList.add('active');
                }
            });
        });
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.available-room-amenities li i')) {
                document.querySelectorAll('.available-room-amenities li i').forEach(function(icon) {
                    icon.classList.remove('active');
                });
            }
        });
    }
    
    function setupLightbox(images) {
        let currentImageIndex = 0;
        const lightbox = document.getElementById('lightbox');
        const lightboxImg = document.getElementById('lightbox-img');
        const closeBtn = document.getElementById('lightbox-close');
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const lightBoxCounter = document.getElementById('lightbox-counter');
    
        const roomImages = document.querySelectorAll('.room-img');
        roomImages.forEach((img, index) => {
            img.addEventListener('click', () => {
                currentImageIndex = index;
                lightbox.style.display = "flex";
                lightboxImg.src = img.src; // Show clicked image
                lightBoxCounter.innerText = `${currentImageIndex + 1} / ${images.length}`;
            });
        });
    
        closeBtn.addEventListener('click', () => {
            lightbox.style.display = "none";
        });
    
        prevBtn.addEventListener('click', () => {
            currentImageIndex = (currentImageIndex === 0) ? images.length - 1 : currentImageIndex - 1;
            lightboxImg.src = images[currentImageIndex].url;
            lightBoxCounter.innerText = `${currentImageIndex + 1} / ${images.length}`;
        });
    
        nextBtn.addEventListener('click', () => {
            currentImageIndex = (currentImageIndex === images.length - 1) ? 0 : currentImageIndex + 1;
            lightboxImg.src = images[currentImageIndex].url;
            lightBoxCounter.innerText = `${currentImageIndex + 1} / ${images.length}`;
        });
    }

    function renderRoom(index) {
        const roomsContainer = document.querySelector('#rooms-container');
        if (roomsContainer) {
            roomsContainer.innerHTML = '';
        }
    
        if (roomsData.length > 0 && index >= 0 && index < roomsData.length) {
            const room = roomsData[index];
            const roomDiv = document.createElement('div');
            roomDiv.className = 'available-room active';
            roomDiv.innerHTML = `
                ${room.is_booked ? `
                    <div class="room-availability">
                        <span class="room-status">Booked, available from: <br /> ${room.next_available_date}</span>
                    </div>
                ` : ''}
                <div class="room-name-radio">
                    <label for="room-${room.id}">${room.name}</label>
                    <input type="hidden" id="room-id" name="room-id" value="${room.id}">
                    ${room.is_booked ? `
                        <input type="submit" id="book-now-button" value="Book Now" disabled="true" style="text-decoration: line-through;">
                    ` : `
                        <input type="submit" id="book-now-button" value="Book Now">
                    `}
                </div>
                <div class="single-room-container">
                    <div class="room-cost">
                        <div class="cost-per-day">${room.currency_symbol}<i>${room.cost_per_day}</i>/night</div>
                        <div class="total-cost">Total: ${room.currency_symbol}<i>${room.total_cost}</i></div>
                    </div>
                    <div class="room-images">
                        ${room.images.length 
                            ? room.images.map((image, index) => `<img class="room-img" src="${image.url}" alt="${room.name}" data-index="${index}" />`).join('') 
                            : `
                                <img class="room-img" src="https://placehold.co/140x140?text=Image+not+available" alt="Placeholder">
                                <img class="room-img" src="https://placehold.co/140x140?text=Image+not+available" alt="Placeholder">
                            `}
                    </div>
                    <div id="lightbox" class="lightbox">
                        <span class="close" id="lightbox-close">&times;</span>
                        <div class="lightbox-content">
                            <img id="lightbox-img">
                            <div class="lightbox-counter" id="lightbox-counter">${index + 1} / ${room.images.length}</div>
                        </div>
                        <a class="prev" id="prev-btn">&#10094;</a>
                        <a class="next" id="next-btn">&#10095;</a>
                    </div>
                    <div class="room-details">
                        <div class="room-size-guests">
                            <div class="room-size">${room.size}m²</div>
                            <div class="room-max-guests">${room.max_guests} guests</div>
                        </div>
                    </div>
                </div>
                ${room.amenities.length ? `
                    <div class="room-amenities">
                        <ul class="available-room-amenities">
                            ${room.amenities.map(amenity => `
                                <li>
                                    <i class="${amenity.icon}" title="${amenity.name}"></i>
                                    <span class="amenity-name">${amenity.name}</span>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                ` : ''}
                ${room.description ? `<div class="room-description"><p>${room.description}</p></div>` : ''}
            `;
            roomsContainer.appendChild(roomDiv);
    
            // Set up event listeners after rendering
            setupLightbox(room.images);
    
            // Set up booking form submission
            document.getElementById('book-now-button').addEventListener('click', function (e) {
                e.preventDefault();
                roomsContainer.remove();
                prevButton.style.display = "none";
                nextButton.style.display = "none";
                const bookingForm = document.createElement('div');
                bookingForm.innerHTML = `
                    <input type="hidden" name="room-id" id="room-id" value="${room.id}">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" required>
                    <label for="email">Email Address:</label>
                    <input type="email" id="email" name="email" required>
                    <label for="phone">Phone Number:</label>
                    <input type="tel" id="phone" name="phone" required>
                    <input type="submit" value="Book Selected Room">`;
                let selectRoomForm = document.querySelector('#select-room-form .form-wrap');
                selectRoomForm.appendChild(bookingForm);
                document.getElementById('select-room-form').addEventListener('submit', function () {
                    document.querySelector('input[type="submit"]').disabled = true;
                });
            });
        }
        amenitiesClickEvent();
    }

    
    function loadRoom(page) {
        const startDateElement = document.getElementById('select-room-start-date');
        const endDateElement = document.getElementById('select-room-end-date');
    
        if (startDateElement && endDateElement) {
            const startDate = startDateElement.value;
            const endDate = endDateElement.value;
    
            if (roomsData.length === 0 || page * 20 >= roomsData.length) {
                const url = `${ajaxScript.ajaxurl}?action=load_room&page=${page}&start_date=${startDate}&end_date=${endDate}&_ajax_nonce=${ajaxScript.nonce}`;
        
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data.rooms.length > 0) {
                            roomsData = data.data.rooms;
                            totalRooms = data.data.total_rooms;
                            renderRoom(currentIndex);
                            updateNavigationButtons();
                        } else {
                            console.log('No rooms found.');
                        }
                    })
                    .catch(error => console.error('Error loading room:', error));
            } else {
                renderRoom(currentIndex);
                updateNavigationButtons();
            }
        }
    }
    
    loadRoom(0);
    
    navigationButtonsEventListener();
    

    function updateNavigationButtons() {
        if(prevButton && nextButton) {
            prevButton.disabled = (currentIndex === 0);
            nextButton.disabled = (currentIndex >= roomsData.length - 1);
        }
    }
    
    function handleGesture() {
        const diffX = Math.abs(touchendX - touchstartX);
        const diffY = Math.abs(touchendY - touchstartY);
        
        if (diffX > diffY) {
            if (touchendX < touchstartX && currentIndex < totalRooms - 1) {
                // Swipe left
                currentIndex++;
                loadRoom(Math.floor(currentIndex / 20));
            }
            if (touchendX > touchstartX && currentIndex > 0) {
                // Swipe right
                currentIndex--;
                loadRoom(Math.floor(currentIndex / 20));
            }
            updateNavigationButtons();
        }
    }
    
    document.addEventListener('touchstart', function(e) {
        touchstartX = e.changedTouches[0].screenX;
        touchstartY = e.changedTouches[0].screenY;
    }, false);
    
    document.addEventListener('touchend', function(e) {
        touchendX = e.changedTouches[0].screenX;
        touchendY = e.changedTouches[0].screenY;
        handleGesture();
    }, false);
    
    function navigationButtonsEventListener () {
        if(nextButton && prevButton) {
            nextButton.addEventListener('click', function() {
                if (currentIndex < roomsData.length - 1) {
                    currentIndex++;
                    loadRoom(Math.floor(currentIndex / 20));
                }
                updateNavigationButtons();
            });
            
            prevButton.addEventListener('click', function() {
                if (currentIndex > 0) {
                    currentIndex--;
                    loadRoom(Math.floor(currentIndex / 20));
                }
                updateNavigationButtons();
            });
        }
    }
    
    function openLightbox(index) {
        roomImages = document.querySelectorAll('.room-img');
        currentImageIndex = index;
        document.getElementById("lightbox").style.display = "flex";
        updateLightboxImage(roomImages, currentImageIndex);
        document.body.classList.add('no-touch');
    }
    
    function closeLightbox() {
        document.getElementById("lightbox").style.display = "none";
        document.body.classList.remove('no-touch');
    }
    
    function changeImage(direction, currentImageIndex) {
        roomImages = document.querySelectorAll('.room-img');
        currentImageIndex += direction;
    
        if (currentImageIndex >= roomImages.length) {
            currentImageIndex = 0; // loop back to first image
        } else if (currentImageIndex < 0) {
            currentImageIndex = roomImages.length - 1; // loop to last image
        }
    
        updateLightboxImage(roomImages, currentImageIndex);
    }
    
    function updateLightboxImage(roomImages, currentImageIndex = 0) {
        const imageElement = roomImages[currentImageIndex];
        const fullSizeImageUrl = imageElement.src; // assuming same src for full size
        document.getElementById("lightbox-img").src = fullSizeImageUrl;

        const lightBoxCounter = document.getElementById('lightbox-counter');
        lightBoxCounter.innerText = `${currentImageIndex + 1} / ${roomImages.length}`;
    }
    
    
});

