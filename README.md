# Akeeba Engage

Comments for Joomla!™ articles made easy

[Downloads](https://github.com/akeeba/engage/releases) • [Documentation](https://github.com/akeeba/engage/wiki) • [Code](https://github.com/akeeba/engage)

> **WORK IN PROGRESS!** Akeeba Engage is currently in pre-alpha and its documentation is incomplete, in the process of being written. Furthermore, the support service for Akeeba Engage is not live yet. This is a preview, not the final product. You're welcome to take a peek but be advised that there may be dragons or other unsightly creatures.

## Executive summary

Akeeba Engage is a simple comment solution for Joomla. It is meant to be used with core content (articles) only. There is no intention of supporting non-core content.

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

Akeeba Engage is currently in pre-alpha stage. There are no public downloads made available yet.

Our plan is to make downloads to the software free of charge through the Releases page of our GitHub repository.

## Documentation

Akeeba Engage is currently in pre-alpha stage. There is no public documentation yet.

Our plan is to offer documentation to Akeeba Engage through the GitHub repository's Wiki system. 

## Support

Akeeba Engage is currently in pre-alpha stage. No support can be provided yet.

Our plan is to provide _paid support_ for issues regarding installing, configuring and using Akeeba Engage once the software reaches a stable version (1.0.0).

Please note that support is meant to be provided in a way to help you overcome issues with the installation, configuration and use of our software. We cannot do the installation and integration work ourselves, nor can we provide support for customising the frontend of the software including but not limited to template and media overrides. 

Furthermore, kindly note that we cannot advise you on configuring your third party WYSIWYG editor; that's something that is best left to the developer of your WYSIWYG editor for objective reasons. We _can_ help to a certain extent with Joomla's built-in WYSIWYG editor (TinyMCE) only.

Finally, please note that we can only provide limited and generic support for core Joomla features such as text filtering and access control (permissions). We can explain how these feature interact with our software and point you to further information – including our public documentation – but we cannot reconfigure or otherwise do extensive work on your site on your behalf.