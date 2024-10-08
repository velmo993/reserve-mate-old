document.addEventListener('DOMContentLoaded', function () {
    var msgModal = document.getElementById('booking-success-modal');
    const imagesToPreload = 2;
    let currentIndex = 0;
    let totalRooms = 0;
    let roomsData = [];
    let filteredRoomsData = [];
    let touchstartX = 0;
    let touchstartY = 0;
    let touchendX = 0;
    let touchendY = 0;
    let adultsNum = 0;
    let childrenNum = 0;
    let storedBookedDays = 1;
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

    function updatePriceDisplay(minValue, maxValue, priceValue) {
        priceValue.textContent = `$${minValue} - $${maxValue}`;
    }
    
    function truncateText(text, wordLimit) {
        const words = text.split(' ');
        if (words.length > wordLimit) {
            return words.slice(0, wordLimit).join(' ') + '...';
        }
        return text;
    }
    
    function toggleDescription(event, link) {
        event.preventDefault();
        
        const roomDescription = link.closest('.room-description');
        const shortDescription = roomDescription.querySelector('.short-description');
        const fullDescription = roomDescription.querySelector('.full-description');
    
        if (shortDescription.style.display === 'none') {
            shortDescription.style.display = 'block';
            fullDescription.style.display = 'none';
            link.textContent = 'More';
            const selectRoomForm = document.querySelector('#select-room-form');
            const formPosition = selectRoomForm.getBoundingClientRect().top + window.pageYOffset;
            const scrollOffset = -50;
    
            window.scrollTo({
                top: formPosition + scrollOffset,
                behavior: 'smooth'
            });
        } else {
            shortDescription.style.display = 'none';
            fullDescription.style.display = 'block';
            link.textContent = 'Less';
        }
    }
            
    function setupFilters() {
        const filterBtn = document.getElementById('filter-btn');
        const resetFiltersBtn = document.getElementById('reset-filters');
        const filterMenu = document.getElementById('filter-menu');
        const closeModal = document.getElementById('close-modal');
        const minPriceRange = document.getElementById('min-price-range');
        const maxPriceRange = document.getElementById('max-price-range');
        const minPriceDefault = minPriceRange ? parseInt(minPriceRange.value) : 0;
        const maxPriceDefault = maxPriceRange ? parseInt(maxPriceRange.value) : 0;
        const priceValue = document.getElementById('price-range-display');
        const roomSizeSelect = document.getElementById('room-size-select');
        const applyFiltersBtn = document.getElementById('apply-filters');
        const priceDifference = 100;
        
        if (filterBtn) {
            filterBtn.addEventListener('click', function(e) {
                e.preventDefault();
                fetchFilterData();
            });
        }
        
        if (closeModal) {
            closeModal.addEventListener('click', function() {
                filterMenu.style.display = 'none'; 
            });
        }
        
        document.addEventListener('click', function(e) {
            if (e.target == filterMenu) {
                filterMenu.style.display = 'none';
            }
        });
        
        if (minPriceRange && maxPriceRange && priceValue) {
            
            minPriceRange.addEventListener('input', function () {
                const minValue = parseInt(minPriceRange.value);
                const maxValue = parseInt(maxPriceRange.value);
                if (maxValue - minValue < priceDifference) {
                    maxPriceRange.value = minValue + priceDifference;
                }
                updatePriceDisplay(minPriceRange.value, maxPriceRange.value, priceValue);
            });
    
            maxPriceRange.addEventListener('input', function () {
                const minValue = parseInt(minPriceRange.value);
                const maxValue = parseInt(maxPriceRange.value);
                if (maxValue - minValue < priceDifference) {
                    minPriceRange.value = maxValue - priceDifference;
                }
                updatePriceDisplay(minPriceRange.value, maxPriceRange.value, priceValue);
            });
    
        }
    
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', function (e) {
                e.preventDefault();
                const selectedAmenities = Array.from(document.querySelectorAll('input[name="amenities"]:checked')).map(cb => cb.value);
                const selectedMinPrice = minPriceRange ? minPriceRange.value : null;
                const selectedMaxPrice = maxPriceRange ? maxPriceRange.value : null;
                const selectedMinRoomSize = roomSizeSelect ? roomSizeSelect.value : null;
    
                applyFilters(selectedMinPrice, selectedMaxPrice, selectedMinRoomSize, selectedAmenities);
                filterMenu.style.display = 'none';
            });
        }
        
        if(resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', function(e) {
                e.preventDefault();
                minPriceRange.value = minPriceDefault;
                maxPriceRange.value = maxPriceDefault;
                roomSizeSelect.value = 'none';
                document.querySelectorAll('input[name="amenities"]:checked').forEach(cb => cb.checked = false);
                applyFilters(null, null, 'none', []);
                updatePriceDisplay(minPriceRange.value, maxPriceRange.value, priceValue);
            });
        }
        
    }
    
    function fetchFilterData() {
        fetch(ajaxScript.ajaxurl + '?action=get_filter_data')
            .then(response => response.json())
            .then(data => {
                const amenitiesContainer = document.getElementById('amenities-container');
                amenitiesContainer.innerHTML = '';
                
                data.amenities.forEach(amenity => {
                    const checkbox = `<label>
                        <input type="checkbox" name="amenities" value="${amenity.amenity_name}">
                        ${amenity.amenity_name}
                    </label>`;
                    amenitiesContainer.insertAdjacentHTML('beforeend', checkbox);
                });
    
                document.getElementById('filter-menu').style.display = 'flex';
            });
    }
    
    function applyFilters(minPrice, maxPrice, minRoomSize, selectedAmenities) {
        filteredRoomsData = roomsData.filter(room => {
            room.base_cost = parseInt(room.base_cost, 10) > 0 ? room.base_cost : (parseInt(room.price_per_adult, 10) * (adultsNum || 1)) + (parseInt(room.price_per_child, 10) * childrenNum);
            const matchesPrice = (!minPrice || parseInt(room.base_cost) >= parseInt(minPrice)) && 
                                 (!maxPrice || parseInt(room.base_cost) <= parseInt(maxPrice));
    
            const matchesRoomSize = minRoomSize === 'none' || parseInt(room.size) >= parseInt(minRoomSize);
    
            const matchesAmenities = selectedAmenities.length === 0 || selectedAmenities.every(selectedAmenity => {
                return room.amenities.some(roomAmenity => roomAmenity.name === selectedAmenity);
            });
    
            return matchesPrice && matchesRoomSize && matchesAmenities;
        });
    
        filteredRoomsData.sort((a, b) => {
            if (a.is_booked && !b.is_booked) return 1;
            if (!a.is_booked && b.is_booked) return -1;
            return 0;
        });
    
        currentIndex = 0;
        renderRoom(currentIndex, filteredRoomsData);
        updateNavigationButtons(filteredRoomsData);
    }

    function daysBooked(startDate, endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const timeDifference = end.getTime() - start.getTime();
        const daysBooked = timeDifference / (1000 * 3600 * 24);

        return daysBooked;
    }
    
    function personalDetailsForm(roomId) {
        const bookingForm = document.createElement('div');
        bookingForm.id = 'booking-form';
        bookingForm.innerHTML = `
            <input type="hidden" name="room-id" id="room-id" value="${roomId}">
            <div class="form-field">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-field">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-field">
                <label for="phone">Phone:</label>
                <input type="tel" id="phone" name="phone" required>
            </div>
            <input type="submit" id="proceed-to-checkout" value="Proceed To Checkout">
        `;
        return bookingForm;
    }
    
    function renderPaymentForm(room, bookingDetails) {
        console.log('renderPaymentForm');
        const paymentForm = document.createElement('div');
        paymentForm.id = 'payment-form';
            
        paymentForm.innerHTML = `
            <div id="payment-options">
                <hr />
                ${paymentSettings.stripe_enabled === "1" ? `
                <div id="stripe-payment-form">
                    <strong>Pay With Card:</strong>
                    <div class="form-field">
                        <div id="card-element" class="form-control"></div>
                        <div id="card-errors" role="alert"></div>
                    </div>
                    <div class="form-field">
                        <input type="submit" id="submit-stripe-payment" value="Pay Now">
                    </div>
                </div>
                <hr />` : ''}

                ${paymentSettings.paypal_enabled === "1" ? `
                <div id="paypal-payment-form">
                    <strong>Pay With PayPal:</strong>
                    <div class="form-field">
                        <div id="paypal-button-container"></div>
                    </div>
                    <input type="hidden" id="paypalPaymentID" name="paypalPaymentID">
                </div>
                <hr />` : ''}
            </div>
            
            <div id="booking-summary">
                <p><strong>Name:</strong><span>${bookingDetails.name}</span></p>
                <p><strong>Phone:</strong><span>${bookingDetails.phone}</span></p>
                <p><strong>Email:</strong><span>${bookingDetails.email}</span></p>
                <p><strong>Room:</strong><span>${bookingDetails.roomName}</span></p>
                <p><strong>Adults:</strong><span>${bookingDetails.adults}</span></p>
                ${bookingDetails.children !== 0 ? `
                <p><strong>Children:</strong><span>${bookingDetails.children}</span></p>
                ` : ''}
                <p><strong>Arrival Date:</strong><span>${bookingDetails.arrivalDate}</span></p>
                <p><strong>Departure Date:</strong><span>${bookingDetails.departureDate}</span></p>
                <p><strong>Total Price:</strong><span>${bookingDetails.currency}${bookingDetails.totalCost}</span></p>
            </div>
        `;
    
        let selectRoomForm = document.querySelector('#select-room-form .form-wrap');
        if (selectRoomForm) {
            console.log('if selectroomform');
            selectRoomForm.appendChild(paymentForm);
    
            if (paymentSettings.stripe_enabled === "1") {
                const event = new Event('stripeFormRendered');
                document.dispatchEvent(event);
            }
    
            if (paymentSettings.paypal_enabled === "1") {
                const event = new Event('paypalFormRendered');
                document.dispatchEvent(event);
            }
        }
    }

    function renderRoom(index, roomsArray = roomsData) {
        const adults = adultsNum;
        const children = childrenNum;
        const bookedDays = storedBookedDays;
        const roomsContainer = document.querySelector('#rooms-container');
        const dataToUse = (filteredRoomsData.length > 0 ? filteredRoomsData : roomsData).sort(sortByAvailability);
    
        if (roomsContainer) {
            roomsContainer.innerHTML = '';
        }
    
        if (index >= 0 && index < dataToUse.length) {
            const room = dataToUse[index];
            const totalRooms = roomsArray.length || roomsData.length;
            const totalCost = calculateTotalCost(room, adults, children, bookedDays);
            const displayedCost = formatDisplayedCost(room, adults, children);
            document.getElementById('total-payment-cost').value = totalCost;
    
            const roomDiv = createRoomDiv(room, index, totalRooms, totalCost, displayedCost);
            roomsContainer.appendChild(roomDiv);
    
            lazyLoadImages();
            setupLightbox(room.images);
            setupBookNowButton(room, totalCost);
            setupReadMoreToggle(roomDiv);
            setupRoomCounter(index, totalRooms);
    
            if (!document.getElementById('booking-form')) {
                document.addEventListener('scroll', checkScrollPosition);
            }
    
            amenitiesClickEvent();
            initSortingButtons();
        }
    }
    
    function sortByAvailability(a, b) {
        if (a.is_booked && !b.is_booked) return 1;
        if (!a.is_booked && b.is_booked) return -1;
        return 0;
    }
    
    function calculateTotalCost(room, adults, children, bookedDays) {
        const baseCost = parseInt(room.base_cost, 10);
        const adultCost = parseInt(room.price_per_adult, 10) * adults;
        const childCost = parseInt(room.price_per_child, 10) * children;
        return ((baseCost + adultCost + childCost) * bookedDays).toFixed(2);
    }
    
    function formatDisplayedCost(room, adults, children) {
        const baseCost = parseInt(room.base_cost, 10);
        if (baseCost > 0) {
            return `${room.currency_symbol}${baseCost}/night`;
        } else {
            const costPerNight = (parseInt(room.price_per_adult, 10) * (adults || 1)) + (parseInt(room.price_per_child, 10) * children);
            return `${room.currency_symbol}${costPerNight}/night`;
        }
    }
    
    function createRoomDiv(room, index, totalRooms, totalCost, displayedCost) {
        const roomDiv = document.createElement('div');
        roomDiv.className = 'available-room active';
    
        roomDiv.innerHTML = `
            <div id="room-counter"></div>
            ${room.is_booked ? `
                <div class="room-availability">
                    <span class="room-status">Booked, available from: <br /> ${room.next_available_date}</span>
                </div>
            ` : ''}
            <div class="room-name-submit">
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
                    <div class="cost-per-day">${displayedCost}</div>
                    <div class="total-cost">Total: ${room.currency_symbol}<i>${totalCost}</i></div>
                </div>
                <div class="room-images">
                    ${room.images.length ? 
                        `${room.images.slice(0, imagesToPreload).map(image => 
                            `<img class="room-img" src="${image.url}" alt="${room.name}" />`
                        ).join('')}
                        ${room.images.slice(imagesToPreload).map(image => 
                            `<img class="room-img lazy-load" data-src="${image.url}" alt="${room.name}" loading="lazy" />`
                        ).join('')}` 
                    : 
                        `<img class="room-img" src="https://placehold.co/140x140?text=Image+not+available" alt="Placeholder">
                        <img class="room-img" src="https://placehold.co/140x140?text=Image+not+available" alt="Placeholder">`
                    }
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
                        <div class="room-size"><i class="fa-solid fa-expand"></i>${room.size}m²</div>
                        <div class="room-max-guests"><i class="fa-solid fa-user-group"></i>${room.max_guests} guests</div>
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
            ${room.description ? `
                <div class="room-description">
                    <p class="short-description">
                        ${truncateText(room.description, 20)}
                        <a href="#" class="read-more">See More</a>
                    </p>
                    <p class="full-description" style="display: none;">${room.description}</p>
                </div>
            ` : ''} 
        `;
    
        return roomDiv;
    }
    
    function setupRoomCounter(index, totalRooms) {
        let roomCounter = document.querySelector('#room-counter');
        if (roomCounter) {
            roomCounter.textContent = `${index + 1} / ${totalRooms}`;
        }
    }
    
    function setupBookNowButton(room, totalCost) {
        const bookNowBtn = document.getElementById('book-now-button');
        if(bookNowBtn) {
            document.getElementById('book-now-button').addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector('.filter-sort-controls').style.display = "none";
                document.querySelector('#rooms-container').remove();
        
                const selectRoomForm = document.querySelector('#select-room-form .form-wrap');
                if (selectRoomForm) {
                    const bookingForm = personalDetailsForm(room.id);
                    selectRoomForm.appendChild(bookingForm);
                    document.getElementById('prev-room').style.display = "none";
                    document.getElementById('next-room').style.display = "none";
        
                    document.getElementById('proceed-to-checkout').addEventListener('click', function (e) {
                        e.preventDefault();
                        handleCheckout(room, totalCost, bookingForm);
                    });
                }
            });
        }
    }
    
    function setupReadMoreToggle(roomDiv) {
        const readMoreLink = roomDiv.querySelector('.read-more');
        if (readMoreLink) {
            readMoreLink.addEventListener('click', (e) => {
                e.preventDefault();
                toggleDescription(e, readMoreLink);
            });
        }
    }
    
    function handleCheckout(room, totalCost, bookingForm) {
        console.log('handlecheckout');
        const name = document.getElementById('name').value;
        const email = document.getElementById('email').value;
        const phone = document.getElementById('phone').value;
        const arrivalDate = document.getElementById('select-room-start-date').value;
        const departureDate = document.getElementById('select-room-end-date').value;
    
        const bookingDetails = {
            roomName: room.name,
            name: name,
            email: email,
            phone: phone,
            adults: adultsNum,
            children: childrenNum,
            bookedDays: storedBookedDays,
            arrivalDate: arrivalDate,
            departureDate: departureDate,
            totalCost: totalCost,
            currency: room.currency_symbol,
        };
    
        if (name && email && phone) {
            bookingForm.style.display = "none";
            renderPaymentForm(room, bookingDetails);
        }
    }
    
    function loadRoom(page) {
        filteredRoomsData = [];
        const adultsElement = document.getElementById('select-room-adults');
        const childrenElement = document.getElementById('select-room-children');
        const startDateElement = document.getElementById('select-room-start-date');
        const endDateElement = document.getElementById('select-room-end-date');
    
        if (adultsElement && childrenElement && startDateElement && endDateElement) {
            const startDate = startDateElement.value;
            const endDate = endDateElement.value;
            const adults = parseInt(adultsElement.value);
            const children = parseInt(childrenElement.value);
            adultsNum = adults;
            childrenNum = children;
            storedBookedDays = daysBooked(startDateElement.value, endDateElement.value);
    
            if (roomsData.length === 0 || page * 20 >= roomsData.length) {
                const url = `${ajaxScript.ajaxurl}?action=load_room&page=${page}&start_date=${startDate}&end_date=${endDate}&_ajax_nonce=${ajaxScript.nonce}`;
        
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data.rooms.length > 0) {
                            roomsData = data.data.rooms;
                            totalRooms = data.data.total_rooms;
                            renderRoom(currentIndex, roomsData);
                            updateNavigationButtons();
                        } else {
                            console.log('No rooms found.');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading room:', error);
                    });
            } else {
                renderRoom(currentIndex, roomsData);
                updateNavigationButtons();
            }
        }
    }
    
    loadRoom(0);

    function updateNavigationButtons() {
        const dataToUse = filteredRoomsData.length > 0 ? filteredRoomsData : roomsData;
        if(prevButton && nextButton) {
            prevButton.disabled = (currentIndex === 0);
            nextButton.disabled = (currentIndex >= dataToUse.length - 1);
        }
    }
    
    function navigationButtonsEventListener () {
        if(nextButton && prevButton) {
            nextButton.removeEventListener('click', handleNextButtonClick);
            prevButton.removeEventListener('click', handlePrevButtonClick);
    
            nextButton.addEventListener('click', handleNextButtonClick);
            prevButton.addEventListener('click', handlePrevButtonClick);
        }
    }
    
    function handleNextButtonClick(e) {
        e.preventDefault();
        const dataToUse = filteredRoomsData.length > 0 ? filteredRoomsData : roomsData;
        if (currentIndex < dataToUse.length - 1) {
            currentIndex++;
            renderRoom(currentIndex, dataToUse);
        }
        updateNavigationButtons(); 
    }
    
    function handlePrevButtonClick(e) {
        e.preventDefault();
        const dataToUse = filteredRoomsData.length > 0 ? filteredRoomsData : roomsData;
        if (currentIndex > 0) {
            currentIndex--;
            renderRoom(currentIndex, dataToUse);
        }
        updateNavigationButtons();
    }
    
    function initializeNavigation() {
        updateNavigationButtons();
        navigationButtonsEventListener();
    }
 
    initializeNavigation();
    setupFilters();
    
    document.addEventListener('touchstart', function(e) {
        touchstartX = e.changedTouches[0].screenX;
        touchstartY = e.changedTouches[0].screenY;
    }, false);
    
    document.addEventListener('touchend', function(e) {
        touchendX = e.changedTouches[0].screenX;
        touchendY = e.changedTouches[0].screenY;
        handleGesture();
    }, false);
    
    function handleGesture() {
        const lightbox = document.getElementById("lightbox");
        
        if (lightbox && lightbox.style.display === "flex") {
            return;
        }
    
        const diffX = Math.abs(touchendX - touchstartX);
        const diffY = Math.abs(touchendY - touchstartY);
        
        if (diffX > diffY) {
            if (touchendX < touchstartX && currentIndex < totalRooms - 1) {
                currentIndex++;
                loadRoom(Math.floor(currentIndex / 20));
            }
            if (touchendX > touchstartX && currentIndex > 0) {
                currentIndex--;
                loadRoom(Math.floor(currentIndex / 20));
            }
            updateNavigationButtons();
        }
    }
    
    function openLightbox(images, lightbox, lightboxImg, lightBoxCounter) {
        lightbox.style.display = "flex";
        lightboxImg.src = images[currentImageIndex].url;
        lightBoxCounter.innerText = `${currentImageIndex + 1} / ${images.length}`;
    }
    
    function closeLightbox(lightbox) {
        lightbox.style.display = "none";
    }
        
    function changeImage(direction, images, lightboxImg, lightBoxCounter) {
        currentImageIndex = (currentImageIndex + direction + images.length) % images.length;
        lightboxImg.src = images[currentImageIndex].url;
        lightBoxCounter.innerText = `${currentImageIndex + 1} / ${images.length}`;
    }
    
    function setupLightbox(images) {
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
                openLightbox(images, lightbox, lightboxImg, lightBoxCounter);
            });
        });
    
        if (lightbox && closeBtn && prevBtn && nextBtn) {
            closeBtn.addEventListener('click', () => closeLightbox(lightbox));
            prevBtn.addEventListener('click', () => changeImage(-1, images, lightboxImg, lightBoxCounter));
            nextBtn.addEventListener('click', () => changeImage(1, images, lightboxImg, lightBoxCounter));
        }
        
        if (lightbox && images && lightboxImg && lightBoxCounter) {
            setupSwipeEvents(lightbox, images, lightboxImg, lightBoxCounter);
        }
    
    }
    
    function setupSwipeEvents(lightbox, images, lightboxImg, lightBoxCounter) {
        let touchstartX = 0;
        let touchendX = 0;
    
        lightbox.addEventListener('touchstart', function(e) {
            touchstartX = e.changedTouches[0].screenX;
        }, false);
    
        lightbox.addEventListener('touchend', function(e) {
            touchendX = e.changedTouches[0].screenX;
            handleLightboxSwipe(touchstartX, touchendX, images, lightboxImg, lightBoxCounter);
        }, false);
    }
    
    function handleLightboxSwipe(touchstartX, touchendX, images, lightboxImg, lightBoxCounter) {
        const diffX = touchendX - touchstartX;
        if (Math.abs(diffX) > 10) {
            if (diffX > 0) {
                changeImage(-1, images, lightboxImg, lightBoxCounter);
            } else {
                changeImage(1, images, lightboxImg, lightBoxCounter);
            }
        }
    }
    
    function checkScrollPosition() {
        const selectRoomForm = document.getElementById('select-room-form');
        const formRect = selectRoomForm.getBoundingClientRect();
        const bookingForm = document.getElementById('booking-form');
        
        if (formRect.bottom < 0 || formRect.top > window.innerHeight || bookingForm) {
            prevButton.style.display = 'none';
            nextButton.style.display = 'none';
        } else {
            prevButton.style.display = 'flex';
            nextButton.style.display = 'flex';
        }
    }
    
    function initSortingButtons() {
        document.getElementById('sort-select').addEventListener('change', (e) => {
            const value = e.target.value;
            const [criteria, order] = value.split('-');
            sortRooms(criteria, order);
        });
    }
    
    function sortRooms(criteria, order) {
        let dataToUse = filteredRoomsData.length > 0 ? filteredRoomsData : roomsData;
        dataToUse.sort((a, b) => {
            let valueA, valueB;
            if (criteria === 'price') {
            const totalCostA = (parseInt(a.base_cost, 10) > 0)
                ? parseInt(a.base_cost, 10)
                : ((parseInt(a.price_per_adult, 10) * adultsNum) + (parseInt(a.price_per_child, 10) * childrenNum)) * storedBookedDays;

            const totalCostB = (parseInt(b.base_cost, 10) > 0)
                ? parseInt(b.base_cost, 10)
                : ((parseInt(b.price_per_adult, 10) * adultsNum) + (parseInt(b.price_per_child, 10) * childrenNum)) * storedBookedDays;

            valueA = totalCostA;
            valueB = totalCostB;

            } else if (criteria === 'size') {
                valueA = a.size;
                valueB = b.size;
            }
    
            if (order === 'asc') {
                return valueA - valueB;
            } else if (order === 'desc') {
                return valueB - valueA;
            }
        });
    
        dataToUse = dataToUse.sort((a, b) => a.is_booked - b.is_booked);
        currentIndex = 0;
        renderRoom(currentIndex, dataToUse);
        updateNavigationButtons();
    }
    
    const lazyLoadImages = () => {
        const images = document.querySelectorAll('.room-img[data-src]');
        const config = {
            rootMargin: '0px 0px 50px 0px',
            threshold: 0.01
        };
    
        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            });
        }, config);
    
        images.forEach(image => {
            observer.observe(image);
        });
    };

    if (msgModal && window.location.href.includes('booking_status=success')) {
        msgModal.classList.add('show');
        setTimeout(() => {
            console.log("Delayed for 4 seconds.");
            msgModal.classList.remove('show');
            
        }, 4000);
    }
    
});

