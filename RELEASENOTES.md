## Joomla and PHP Compatibility

We are developing, testing and using Akeeba Engage using the latest version of Joomla! and a popular and actively maintained branch of PHP 7. At the time of this writing this is:

* Joomla! 3.9 and 4.0 (pre-beta1 development)
* PHP 7.4

Akeeba Engage should be compatible with:
* Joomla! 3.9 and 4.0
* PHP 7.1, 7.2, 7.3 and 7.4.

## Changelog

_Initial public release._

**New features**

* Comments allow for full HTML, edited using the WYSIWYG editor configured in Joomla. 
* HTML filtering for comments either by Joomla itself or using the more robust, heavily audited HTML Purifier library.
* Comments can be filed by logged in users or guests (configurable with Joomla's permissions).
* Guest commenters' information can be remembered across sessions on the same browser.
* Custom module positions for enriching the comments output without overriding the view templates.
* Guest users can see a module or a module position to help them log in to file a comment (fully compatible with Akeeba SocialLogin).
* Control open/closed comments, comments autoclose and comments display globally, per category and per article.
* Comments are hierarchical, i.e. you can file a comment in reply to another comment.
* Avatars using the Gravatar service.
* You can optionally require a CAPTCHA for guest comments, non-special users' comments or for all comments. You can use any CAPTCHA supported by Joomla, installed and enabled on your site.
* Comments can be checked for spam using Akismet (paid, third party service).
* Notifications about comments can be sent to managers and optionally the participants of a conversation using customizable email templates.
* wbAMP support for comments display on AMP pages (you can only file new comments and reply to comments in the full HTML view, though).
* Fully semantic output with Schema.org tagging using microdata in both HTML and AMP outputs.
* Dark Mode support, front- and backend.
* Integration with Joomla's Privacy (com_privacy) and Akeeba DataCompliance components for GDPR compliance i.e. data export and implementation of user content deletion.
* Integration with User Actions, logging administrative actions taken on comments.
* Full support for Joomla caching (Conservative and Progressive).
* You can customize the comments display with standard Joomla template overrides.
* You can customize the CSS used for comments display with standard Joomla media overrides. You get the full SCSS source files.
* Downloads and updates to the component are free of charge. Getting support will cost money, though.