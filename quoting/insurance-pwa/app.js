// Register the service worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
            .then(registration => {
                console.log('Service Worker registered with scope:', registration.scope);
            })
            .catch(error => {
                console.error('Service Worker registration failed:', error);
            });
    });
}

// Check authentication status to prevent unauthorized access
if (!['/index.html', '/', '/insurance-pwa/index.html'].includes(window.location.pathname) && !localStorage.getItem('client_id') && !localStorage.getItem('user_id')) {
    console.log('Redirecting to index.html due to missing authentication');
    window.location.href = 'index.html';
}

// Handle login form submission
document.getElementById('loginForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.classList.add('d-none');
    errorMessage.textContent = '';

    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    try {
        const response = await fetch('../login_management/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json'
            },
            body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
            if (data.user_type === 'client') {
                localStorage.setItem('client_id', data.client_id);
                localStorage.setItem('client_name', data.client_name);
                localStorage.setItem('user_type', 'client');
                window.location.href = 'client_details.php';
            } else {
                localStorage.setItem('user_id', data.user_id);
                localStorage.setItem('role_name', data.role_name);
                localStorage.setItem('brokerage_id', data.brokerage_id);
                localStorage.setItem('user_type', 'staff');
                window.location.href = 'index.html';
            }
        } else {
            errorMessage.textContent = data.error || 'Invalid email/ID number or password';
            errorMessage.classList.remove('d-none');
        }
    } catch (error) {
        console.error('Login error:', error);
        errorMessage.textContent = 'An error occurred. Please try again later.';
        errorMessage.classList.remove('d-none');
    }
});

// Handle logout
document.querySelectorAll('a[href="/logout"]').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        localStorage.clear();
        window.location.href = 'index.html';
    });
});

// Client Details Page Functionality
if (window.location.pathname.includes('client_details.php')) {
    async function fetchClientData() {
        try {
            const response = await fetch('../quote_management/get_client_data.php');
            if (!response.ok) throw new Error('Failed to fetch client data');
            const data = await response.json();
            if (data.error) {
                document.getElementById('errorMessage').textContent = data.error;
                document.getElementById('errorMessage').classList.remove('d-none');
                return;
            }
            // Add event listeners for inspection buttons to handle navigation to inspection_photos.php
            document.querySelectorAll('.btn-purple[href*="inspection_photos.php"]').forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    const url = new URL(button.href);
                    const quoteId = url.searchParams.get('quote_id');
                    if (!quoteId || isNaN(quoteId) || parseInt(quoteId) <= 0) {
                        console.error('Invalid quote_id for inspection button:', { quoteId, href: button.href });
                        const errorMessage = document.getElementById('errorMessage');
                        errorMessage.textContent = 'Invalid quote ID for inspection. Please contact support.';
                        errorMessage.classList.remove('d-none');
                    } else {
                        if (confirm('Are you sure you want to proceed with the vehicle inspection?')) {
                            console.log('Navigating to inspection_photos.php with quote_id:', quoteId);
                            window.location.href = button.href;
                        } else {
                            console.log('Inspection navigation cancelled by user for quote_id:', quoteId);
                        }
                    }
                });
            });

            // Populate client details
            document.getElementById('client_name').textContent = data.client.name || 'N/A';
            document.getElementById('client_id').textContent = data.client.client_id || 'N/A';
            document.getElementById('client_email').textContent = data.client.email || 'N/A';
            document.getElementById('cell_number').textContent = data.personal_details.cell_number || 'N/A';
            document.getElementById('sms_consent').textContent = data.personal_details.sms_consent || 'N/A';
            document.getElementById('physical_address').textContent = data.personal_details.physical_address || 'N/A';
            document.getElementById('postal_address').textContent = data.personal_details.postal_address || 'N/A';

            // Populate vehicles as cards
            const vehiclesContainer = document.getElementById('vehicles_container');
            vehiclesContainer.innerHTML = '';
            if (!Array.isArray(data.vehicles) || data.vehicles.length === 0) {
                console.log('No vehicles found or invalid vehicles data:', data.vehicles);
                vehiclesContainer.innerHTML = '<p>No insured vehicles</p>';
            } else {
                console.log('Vehicles data:', data.vehicles);
                data.vehicles.forEach((vehicle, index) => {
                    if (!vehicle || !vehicle.vehicle_year || !vehicle.vehicle_make || !vehicle.vehicle_model) {
                        console.warn(`Invalid vehicle data at index ${index}:`, vehicle);
                        return;
                    }
                    vehiclesContainer.innerHTML += `
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">${vehicle.vehicle_year} ${vehicle.vehicle_make} ${vehicle.vehicle_model}</h6>
                                <p><strong>Policy ID:</strong> ${vehicle.policy_id || 'N/A'}</p>
                                <p><strong>Coverage Type:</strong> ${vehicle.coverage_type || 'N/A'}</p>
                                <p><strong>Security Requirements:</strong> ${vehicle.security_device || 'None'}</p>
                                <p><strong>Premium:</strong> R ${vehicle.premium_amount ? parseFloat(vehicle.premium_amount).toFixed(2) : 'N/A'}</p>
                                <p><strong>Inspection Status:</strong> ${vehicle.inspection_status || 'Pending'}</p>
                                ${vehicle.quote_id && vehicle.inspection_status !== 'Completed' ? 
                                    `<a href="inspection_photos.php?quote_id=${vehicle.quote_id}" class="btn btn-purple btn-sm">Complete Inspection</a>` : 
                                    `<button class="btn btn-purple btn-sm" disabled>${vehicle.quote_id ? 'Inspection Completed' : 'Inspection Unavailable'}</button>`
                                }
                            </div>
                        </div>
                    `;
                });
            }

            // Populate debit order details
            document.getElementById('debit_date').textContent = data.debit_order.debit_date;
            document.getElementById('debit_premium').textContent = data.debit_order.debit_premium;
        } catch (error) {
            console.error('Error fetching client data:', error);
            document.getElementById('errorMessage').textContent = 'Error loading client data';
            document.getElementById('errorMessage').classList.remove('d-none');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (localStorage.getItem('user_type') !== 'client') {
            window.location.href = 'index.html';
        } else {
            fetchClientData();
        }
    });
}