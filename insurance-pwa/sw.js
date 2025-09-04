const CACHE_NAME = 'insurance-pwa-cache-v1';
const urlsToCache = [
    './',
    './inspection_photos.php',
    './client_details.php',
    './app.js',
    './styles.css',
    './images/logo.png',
    './images/examples/left_side_example.jpg',
    './images/examples/right_side_example.jpg',
    './images/examples/front_example.jpg',
    './images/examples/back_example.jpg',
    './images/examples/bonnet_open_example.jpg',
    './images/examples/license_disc_example.jpg',
    './images/examples/odometer_example.jpg',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            console.log('Caching resources:', urlsToCache);
            const cachePromises = urlsToCache.map(url => {
                return fetch(url, { mode: url.startsWith('http') ? 'no-cors' : 'same-origin' })
                    .then(response => {
                        if (!response.ok && response.status !== 0) {
                            console.error(`Failed to fetch ${url}: Status ${response.status}`);
                            return null;
                        }
                        console.log(`Successfully fetched ${url}`);
                        return cache.put(url, response);
                    })
                    .catch(err => {
                        console.error(`Failed to fetch ${url}:`, err);
                        return null;
                    });
            });
            return Promise.all(cachePromises).then(() => {
                console.log('All valid resources cached');
            });
        }).catch(err => {
            console.error('Cache open failed:', err);
        })
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request).then(response => {
            if (response) {
                console.log(`Serving ${event.request.url} from cache`);
                return response;
            }
            return fetch(event.request).catch(err => {
                console.error('Fetch failed for:', event.request.url, err);
                return caches.match('./client_details.php');
            });
        })
    );
});

self.addEventListener('activate', event => {
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (!cacheWhitelist.includes(cacheName)) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});