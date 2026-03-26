# Struktur Project

## Ringkasan
Struktur berikut dirancang untuk PHP Native yang ringan, mudah dibaca, dan tetap modular. Tidak ada file routing terpisah; resolver route berada di bootstrap internal.

## Struktur Direktori Final
```text
albion/
|-- app/
|   |-- Controllers/
|   |-- Middleware/
|   |-- Services/
|   |-- Repositories/
|   |-- Views/
|   `-- Support/
|-- assets/
|   |-- components/
|   |-- css/
|   |-- js/
|   `-- icons/
|-- bootstrap/
|-- config/
|-- docs/
|-- public/
|-- storage/
|-- vendor/
|-- package.json
|-- tailwind.config.js
|-- postcss.config.js
`-- .env.example
```

## Detail Struktur
```text
app/Controllers/
- AuthController.php
- DashboardController.php
- CalculatorController.php
- PriceDataController.php
- SubscriptionController.php
- ReferralController.php

app/Middleware/
- AuthMiddleware.php
- GuestMiddleware.php
- SubscriptionMiddleware.php
- PlanFeatureMiddleware.php

app/Services/
- AuthService.php
- SubscriptionService.php
- ReferralService.php
- CalculationEngineService.php
- MarketPriceService.php

app/Repositories/
- UserRepository.php
- SubscriptionRepository.php
- ReferralRepository.php
- ItemRepository.php
- RecipeRepository.php
- MarketPriceRepository.php

app/Support/
- Router.php
- Request.php
- Response.php
- Validator.php
- Session.php
- Csrf.php
```

## Aturan Struktur
- `app/Controllers` hanya untuk koordinasi request/response.
- `app/Services` adalah pusat business logic.
- `app/Repositories` memegang query database dan mapping hasil query.
- `app/Views` hanya render HTML.
- `assets/components` memegang reusable classes dan partial CSS components.
- `public/assets` berisi hasil build final yang bisa diakses browser.

## Naming Convention
- Kelas PHP memakai PascalCase.
- Method memakai camelCase.
- Tabel database memakai snake_case.
- Konstanta plan dan return type memakai huruf besar.

## Prinsip View dan Styling
- View dilarang styling inline.
- View hanya memakai nama class reusable yang sudah didefinisikan.
- Utility Tailwind tetap boleh dipakai melalui kelas komponen yang dibungkus di `assets/components`, bukan ditempel acak di setiap view.
