# 1.0.1

**New features**

* Checkbox to accept ToS / give consent (gh-46)

# 1.0.0

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

# 1.0.0.b2

**Bug fixes**

* Immediate site crash because of a missing check in the cache handler plugin if no other system plugin has already loaded the FOF 3 library.

# 1.0.0.b1

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