## Joomla and PHP Compatibility

We are developing, testing and using Akeeba Engage using the latest version of Joomla! and a popular and actively maintained branch of PHP 7. At the time of this writing this is:

* Joomla! 3.9 and 4.0 (pre-beta1 development)
* PHP 7.4

Akeeba Engage should be compatible with:
* Joomla! 3.9 and 4.0
* PHP 7.1, 7.2, 7.3 and 7.4.

## Changelog

**Bug fixes**

* Immediate site crash because of a missing check in the cache handler plugin if no other system plugin has already loaded the FOF 3 library.
