document.addEventListener('DOMContentLoaded', function () {
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
    
    function renderPaymentForm(room) {
        const paymentForm = document.createElement('div');
        paymentForm.innerHTML = `
            <div class="form-group">
                <label>Choose Payment Method:</label>
                <div class="payment-methods">
                    <button type="button" class="payment-button" data-method="stripe">Credit Card (Stripe)</button>
                    <button type="button" class="payment-button" data-method="paypal">PayPal</button>
                    <!-- Add more buttons for other payment methods if needed -->
                </div>
            </div>
            <div id="payment-details"></div>
        `;
    
        let selectRoomForm = document.querySelector('#select-room-form .form-wrap');
        if (selectRoomForm) {
            selectRoomForm.appendChild(paymentForm);
    
            let stripeFormCreated = false;
            let payPalFormCreated = false;
    
            paymentForm.addEventListener('click', function (e) {
                if (e.target.classList.contains('payment-button')) {
                    const selectedMethod = e.target.getAttribute('data-method');
                    const paymentDetails = document.getElementById('payment-details');
    
                    if (selectedMethod === 'stripe') {
                        if (!stripeFormCreated) {
                            renderStripePayment(paymentDetails, room);
                            stripeFormCreated = true;
                            
                        } else {
                            document.getElementById('stripe-payment-form').classList.toggle('hidden');
                        }
                        if (payPalFormCreated) {
                            paymentDetails.querySelector('#paypal-payment-form').remove();
                            payPalFormCreated = false;
                        }
                    } else if (selectedMethod === 'paypal') {
                        if (!payPalFormCreated) {
                            renderPayPalPayment(paymentDetails, room);
                            payPalFormCreated = true;
                            
                        } else {
                            document.getElementById('paypal-payment-form').classList.toggle('hidden');
                        }
                        if (stripeFormCreated) {
                            paymentDetails.querySelector('#stripe-payment-form').remove();
                            stripeFormCreated = false;
                        }
                    }
                }
            });
        }
    }
    
    function renderStripePayment(paymentDetails, room) {
        const stripeForm = document.createElement('div');
        stripeForm.id = 'stripe-payment-form';
        stripeForm.innerHTML = `
            <div class="form-group">
                <label for="card-element">Credit or debit card</label>
                <div id="card-element" class="form-control"></div>
            </div>
            <div id="card-errors" role="alert"></div>
            <input type="submit" id="submit-stripe-payment" value="Pay Now">
        `;
    
        paymentDetails.appendChild(stripeForm);
    
        const event = new Event('stripeFormRendered');
        document.dispatchEvent(event);
    }
    
    function renderPayPalPayment(paymentDetails, room) {
        const paypalForm = document.createElement('div');
        paypalForm.id = 'paypal-payment-form';
        paypalForm.innerHTML = `
            <div id="paypal-button-container"></div>
        `;
    
        paymentDetails.appendChild(paypalForm);
    
        const event = new Event('paypalFormRendered');
        document.dispatchEvent(event);
    }

    function renderRoom(index, roomsArray = roomsData) {
        const adults = adultsNum;
        const children = childrenNum;
        const bookedDays = storedBookedDays;
        const roomsContainer = document.querySelector('#rooms-container');
        
        const dataToUse = (filteredRoomsData.length > 0 ? filteredRoomsData : roomsData).sort((a, b) => {
            if (a.is_booked && !b.is_booked) return 1;
            if (!a.is_booked && b.is_booked) return -1;
            return 0;
        });
        
        if (roomsContainer) {
            roomsContainer.innerHTML = '';
        }
    
        if (index >= 0 && index < dataToUse.length) {
            const room = dataToUse[index];
            const totalRooms = roomsArray.length || roomsData.length;
            const currentRoomNumber = index + 1;
            const roomDiv = document.createElement('div');
            const totalCost = (parseInt(room.base_cost, 10) 
                + (parseInt(room.price_per_adult, 10) * adults) 
                + (parseInt(room.price_per_child, 10) * children)).toFixed(2) * bookedDays;
            const baseCost = parseInt(room.base_cost, 10);
            let displayedCost = baseCost > 0 ? `${room.currency_symbol}${baseCost}/night` : `${room.currency_symbol}${(parseInt(room.price_per_adult, 10) * (adults || 1)) + (parseInt(room.price_per_child, 10) * children)}/night`;

            roomDiv.className = 'available-room active';
            roomDiv.innerHTML = `
                <div id="room-counter"></div>
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
                        <div class="cost-per-day">${displayedCost}</div>
                        <div class="total-cost">Total: ${room.currency_symbol}<i>${totalCost}</i></div>
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
                ${room.description ? `
                    <div class="room-description">
                        <p class="short-description">${truncateText(room.description, 20)}</p>
                        <p class="full-description" style="display: none;">${room.description}</p>
                        <a href="#" class="read-more">More</a>
                    </div>
                ` : ''}
            `;
            roomsContainer.appendChild(roomDiv);
        
            setupLightbox(room.images);
            
            let roomCounter = document.querySelector('#room-counter');
            
            if (roomCounter) {
                roomCounter.textContent = `${currentRoomNumber} / ${totalRooms}`;
            }
            
            document.getElementById('book-now-button').addEventListener('click', function (e) {
                e.preventDefault();
                roomsContainer.remove();
                document.querySelector('.filter-sort-controls').style.display = "none";
                prevButton.style.display = "none";
                nextButton.style.display = "none";
    
                let selectRoomForm = document.querySelector('#select-room-form .form-wrap');
                if (selectRoomForm) {
                    const bookingForm = personalDetailsForm(room.id);
                    selectRoomForm.appendChild(bookingForm);
    
                    document.getElementById('proceed-to-checkout').addEventListener('click', function (e) {
                        e.preventDefault();
    
                        const name = document.getElementById('name').value;
                        const email = document.getElementById('email').value;
                        const phone = document.getElementById('phone').value;
    
                        if (name && email && phone) {
                            bookingForm.style.display = "none";
                            renderPaymentForm(room);
                        }
                    });
                }
            });
            
            const readMoreLink = roomDiv.querySelector('.read-more');
            if (readMoreLink) {
                readMoreLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    toggleDescription(e, readMoreLink);
                });
            }
        }
        amenitiesClickEvent();
        initSortingButtons();
        if(!prevButton.style.display === "none") {
            window.addEventListener('scroll', checkScrollPosition);
        }
    }
    
    function loadRoom(page) {
        filteredRoomsData = [];
        const adults = parseInt(document.getElementById('select-room-adults').value) || 1;
        const children = parseInt(document.getElementById('select-room-children').value) || 1;
        adultsNum = adults;
        childrenNum = children;
        
        const startDateElement = document.getElementById('select-room-start-date');
        const endDateElement = document.getElementById('select-room-end-date');
        storedBookedDays = daysBooked(startDateElement.value, endDateElement.value);
        
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
                            renderRoom(currentIndex, roomsData);
                            updateNavigationButtons();
                        } else {
                            console.log('No rooms found.');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading room:');
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
    
        closeBtn.addEventListener('click', () => closeLightbox(lightbox));
    
        prevBtn.addEventListener('click', () => changeImage(-1, images, lightboxImg, lightBoxCounter));
    
        nextBtn.addEventListener('click', () => changeImage(1, images, lightboxImg, lightBoxCounter));
    
        setupSwipeEvents(lightbox, images, lightboxImg, lightBoxCounter);
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
        
        if (formRect.bottom < 0 || formRect.top > window.innerHeight) {
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
    
    
});

