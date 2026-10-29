
Lets SSH


ssh master_mgexgxgevm@46.101.27.90

cd applications
cd fdcpgwbqxd
cd public_html



# MySQL: use credentials from the Cloudways application dashboard (do not commit passwords).

mysql -ufdcpgwbqxd -p

use fdcpgwbqxd;


------------------------------------


php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize:clear
php artisan filament:optimize


----------------------------------------------
--------------STAGING--------------
ssh master_mgexgxgevm@46.101.27.90

cd applications
cd eeyfxyvxmv
cd public_html

mysql -ueeyfxyvxmv -p

use eeyfxyvxmv;

----------------------------------------------


git checkout staging
# do your work...
git add .
git commit -m "CRM"
git push origin staging





git checkout main
git merge staging
git push origin main

git checkout staging

then pull from live


