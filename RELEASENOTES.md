## Joomla and PHP Compatibility

We are developing, testing and using Akeeba Engage using the latest version of Joomla! and a popular and actively maintained branch of PHP 7. At the time of this writing this is:

* Joomla! 3.9 and 4.0 (pre-beta1 development)
* PHP 7.4

Akeeba Engage should be compatible with:
* Joomla! 3.9 and 4.0
* PHP 7.1, 7.2, 7.3 and 7.4.

## Changelog

**New features**

* Partial Spanish translation
* Partial Greek translation
* Improve the backend UI for comments on deleted / invalid content
* Delete comments on article deletion
* Option to “own” comments on login (gh-37) 

**Miscellaneous changes**

* Ability to display no login module if you select the option "( Do not show )" and publish no other module in the `engage-login` position
* User Action Log entries translate the content type, useful for languages other than English
* There's now a Clear button for the Comments filters in the backend

**Bug fixes**

* Login module displayed even when guest comments are enabled
* Privacy component crash if Privacy – Akeeba Engage plugin is published first in its group
* The User Action Log plugin shouldn't have options for Guest users
* Shared Sessions can lead to comments not being displayed if the manager applies filters in the backend Comments page
* User / manager icon overlap with the username in Joomla 4 frontend
* Filing comments as a user could fail due to misidentified asset tracking in the model
* Comments always published, despite setting New Comments to Unpublished (gh-44) 
* Unhandled exception page was incompatible with Joomla 4
* Comments starting with a tag other than p or div would appear as raw HTML instead of being formatted
