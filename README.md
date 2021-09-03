# Akeeba Engage

Comments for Joomla!™ articles made easy

[Downloads](https://www.akeeba.com/download/official/engage.html) • [Documentation](https://github.com/akeeba/engage/wiki) • [Code](https://github.com/akeeba/engage)

## Executive summary

Akeeba Engage is a simple comment solution for Joomla. It is meant to be used with core content (articles) only. 

There is no intention of supporting non-core content.

## Requirements

* Joomla 3.9 or 4.0.
* PHP 7.1 or later (7.3 recommended).

## Features

* Downloads and updates to the component are free of charge. WE DO NOT PROVIDE ANY END USER SUPPORT.
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

## Downloads

You can download Akeeba Engage free of charge from its [Downloads page](https://www.akeeba.com/download/official/engage.html).

## Documentation

You can access Akeeba Engage's documentation free of charge on its GitHub repository's [documentation wiki](https://github.com/akeeba/engage/wiki).

## Languages

**VERY IMPORTANT: The language files included with Akeeba Engage are third party contributions. They have not and cannot be validated by Akeeba Ltd. Moreover, we cannot offer support for them. Please contact the respective translators for any inquiries, suggestions or language changes. Akeeba Ltd has only written and will only offer support for the English (Great Britain) and Greek (Greece) language files. Thank you for your understanding!**

We are only interested in including the following languages in Akeeba Engage's download package:

* `en-GB`  English (Great Britain). Joomla's default language. This language is maintained by Akeeba Ltd.
* `el-GR`  Greek (Greece). This language is maintained by Akeeba Ltd.
* `de-DE`  German (Germany).
* `fr-FR`  French (France).
* `es-ES`  Spanish (Spain).
* `nl-NL`  Dutch (Netherlands).
* `it-IT`  Italian (Italy).

If you are willing to translate to a specific language please file a GitHub issue. Your language must be contributed as a Pull Request to this GitHub repository.

Other languages, including regional variations of the aforementioned languages (e.g. Austrian German), are not a good fit for inclusion in this repository and Akeeba Engage's download package. 

You are free to create your own installable language packages as long as you follow the terms of the GNU General Public License version 3, or any later version published by the Free Software Foundation. Our language files are covered by the same license as our software, therefore your derivative works need to be covered by this license as well. 

We do not plan on listing third party language files anywhere on our site or this repository. We recommend that you ask your local Joomla! User Group on the best way to disseminate them to people speaking your language.

## Support

We do not provide any end user support for this software. We do provide free of charge [Documentation](https://github.com/akeeba/engage/wiki).

We will accept feature requests _within reason_. In an effort to not waste anybody's time, here is a non–exhaustive list of feature requests we will not consider and why:

* Requests to change fundamental architectural choices about this extension e.g. that all comments are HTML, that we're using Joomla's WYSIWYG HTML editor, that we only support core articles etc. Fundamental architectural choices are not up for debate or change for obvious reasons (i.e. it would be a different extension, not Akeeba Engage!).
* Requests which boil down to adding options regarding the appearance or messages. We have intentionally made it possible for you to create standard Joomla template and language overrides, respectively, as a more powerful and flexible alternative. If you prefer drop–downs and checkboxes to template overrides Akeeba Engage is not for you.
* Any kind of email receiving features such as but not limited to administering, submitting or replying to comments by email. We have experience doing that in our ticket system and we know all the reasons why it sounds like a great idea but it's a maintenance nightmare _for the site owner_. If you really want this feature you should pay for Disqus instead. We are dead serious.
* Mass–notification of users beyond the emails sent within a comment reply thread. There are performance issues which make such a feature impractical when more than 5–10 people are going to be notified by email. Even if _your_ use case is only limited to this small number of people, for most folks using our extension it will be much higher and cause their sites to fail. Sure we could add a warning… that nobody would read.

Kindly note that whether any requested features will be implemented is up to our sole discretion.