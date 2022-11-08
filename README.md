# ZGWBundle

Symfoyn bundle for zgw standard functionality

#### Using your code

To use the code in your library we first have to install it with composer.

Note: for docker add `docker-compose exec php` before all comands

1. Navigate with a command line to where your composer.json lives in the project you want to use this bundle.
   - Execute `composer require {full package name}:dev-main`
   - Docker users: restart your containers so symfony can recognize the new Bundle's namespace
2. Open a php file where you want to use a class.
   - Add the correct use statement (example `use CommonGateway\PetStoreBundle\Service\PetStoreService;`)
   - U can now use your class!

In the common gateway, if you want to use your code when triggered by an event with a action, make sure the class of the action object is set as the handler name including the namespace. For example if I want to use the PetStoreService I can set the PetStoreHandler as `CommonGateway\PetStoreBundle\ActionHandler\PetStoreHandler`.
