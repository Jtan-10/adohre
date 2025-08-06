#!/bin/bash

echo "🔄 Restarting Bitnami services..."
sudo /opt/bitnami/ctlscript.sh restart

echo "✅ Services restarted successfully!"
echo ""
echo "🧪 Now test your application by:"
echo "1. Visiting: http://3.1.93.167/capstone-php/check_login_status.php"
echo "2. Logging in via: http://3.1.93.167/capstone-php/login.php"
echo "3. Checking session persistence after login"
echo ""
echo "All session configurations have been updated to use:"
echo "- FORCE_SECURE_COOKIES=false (from .env)"
echo "- configureSessionSecurity() function"
echo "- Consistent session parameters across all files"
