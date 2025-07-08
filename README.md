# Onest LMS Module for PipraPay

This module integrates PipraPay payment gateway into the Onest LMS system.

## Setup Instructions

1. Open your `.env` file and add the following entries:

   ```env
   PP_BASE_URL="https://pay.yoursubdomain.com/api"
   PP_API_KEY="YOUR_API_KEY"
   PP_CURRENCY="BDT"
   ```

2. Navigate to `app/Http/Middleware/VerifyCsrfToken.php` and add the following route to the `$except` array:

   ```php
   '/payments/verify/piprapay',
   ```

3. Upload the `piprapay` folder to the following location in your project:

   ```
   Modules/Payment/PaymentMethods/
   ```

4. Done! Your PipraPay payment gateway module should now be integrated.

---

If you encounter any issues or have questions, feel free to open an issue or contact the maintainer.
