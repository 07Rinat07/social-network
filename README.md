<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>


### ABOUT project_Social-network SPA
Project "Social Network" - made on Laravel and Vue frameworks, Tailwindcss & Vite styles are also used. The project will be deployed on the hosting for 
demonstration and testing.

### Project written on Laravel 9.5&&Vue&&TailwindcInstructions and additional information for installing and testing the application: 
1. Link how-to-install-vue-3-in-laravel-9-with-vite: https://techvblogs.com/blog/how-to-install-vue-3-in-laravel-9-with-vite
2. Link Get started with Tailwind CSS: https://tailwindcss.com/docs/installation/using-postcss
3. composer install or composer update
4. composer require laravel/ui
5. php artisan ui:auth
6. Create a DB (in the .env file and the database, enter the correct data for configuration)
7. php artisan migrate
8. If you want to deploy this site on hosting, then in .env
   write a constant
   SANCTUM_STATEFUL_DOMAINS=AND_HERE_YOUR_DOMAINS_OR_IP
   For example, I have
   SANCTUM_STATEFUL_DOMAINS=41.155.158.48
9. If you will deploy the site to a hosting, then first build for VUE locally or on the hosting if you have access to Node and SSH.


### To run the project locally, you need to type commands in the terminal in turn: 

1. npm run dev 
2. php artisan serve
3. php artisan storage:link

### Additional actions in case of errors...
1. php artisan route:cache
2. php artisan route:clear
3. php artisan config:clear
4. php artisan cache:clear
5. php artisan optimize
