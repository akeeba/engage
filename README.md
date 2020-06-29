# Akeeba Engage

Comments for Joomla!™ articles made easy

[Downloads](https://www.akeeba.com/download/official/engage.html) • [Documentation](https://github.com/akeeba/engage/wiki) • [Code](https://github.com/akeeba/engage)

## Executive summary

Akeeba Engage is a simple comment solution for Joomla. It is meant to be used with core content (articles) only. 

There is no intention of supporting non-core content.

## Requirements

* Joomla 3.9 of 4.0.
* PHP 7.1 or later (7.3 recommended).

## Features

* Downloads and updates to the component are free of charge. Getting support costs money, though.
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

Akeeba Engage is currently in beta stage. Limited support will be provided through GitHub issues. However, this is not a permanent solution.

Our plan is to provide _paid support_ for issues regarding installing, configuring and using Akeeba Engage once the software reaches a stable version (1.0.0).

Please note that support is meant to be provided in a way to help you overcome issues with the installation, configuration and use of our software. We cannot do the installation and integration work ourselves, nor can we provide support for customising the frontend of the software including but not limited to template and media overrides. We give you the tools to do that but we don't do custom integration and customization work on your behalf. 

Furthermore, kindly note that we cannot advise you on configuring your third party WYSIWYG editor beyond what we have already documented in the Wiki; that's something that is best left to the developer of your WYSIWYG editor for objective reasons.

Finally, please note that we can only provide limited and generic support for core Joomla features such as text filtering and access control (permissions). We can explain how these feature interact with our software and point you to further information – including our public documentation – but we cannot reconfigure or otherwise do extensive work on your site on your behalf.