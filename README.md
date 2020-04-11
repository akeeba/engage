# Akeeba Engage

Comments for Joomla!™ articles made easy

## WORK IN PROGRESS

Akeeba Engage is currently in pre-alpha stage, under active development. It is not yet stable enough for use on production sites.

## Executive summary

Akeeba Engage is a very simple comment solution for Joomla. It is meant to be used with core content (articles) only. There is no intention of supporting non-core content.

## Requirements

* Joomla 3.9
* PHP 7.1 or later (7.3 recommended)

## Features

* Comments allow for full HTML, edited using the WYSIWYG editor configured in Joomla. HTML filtering is performed by Joomla itself; if you didn't already know, you can set it up in the Global Configuration of your site.
* Comments can be filed by logged in users or guests. Who can file comment on which category and article is fully configurable through Joomla's access control, category and article settings. 
* Comments are hierarchical, i.e. you can file a comment in reply to another comment. You can choose the maximum hierarchy level. The default is up to 3 levels deep, like in most other comment systems.
* You can optionally require a CAPTCHA for guest comments, non-special users' comments or for any comment. You can use any CAPTCHA supported by Joomla, installed and enabled on your site.
* **Planned:** Comments can be checked for spam using Akismet and Project Honeypot.
* **Planned:** Notifications about comments can be sent to managers and optionally the participants of a conversation using customizable email templates.
* **Planned:** Integration with Joomla's Privacy (com_privacy) and Akeeba DataCompliance components for GDPR compliance i.e. data export and implementation of user content deletion. Note that this only applies to users who filed comments while logged in.
* **Planned:** Integration with User Actions, logging administrative actions taken on comments. Filing a comment is _not_ logged.
* **Planned:** Dark Mode support
* You can customize the comments display with standard Joomla template overrides.
* You can customize the CSS used for comments display with standard Joomla media overrides.
* **Planned:** wbAMP support for comments display in AMP representation of your article pages.
* **Planned (for after 1.0.0):** Avatar support beyond Gravatar.

## Downloads

Akeeba Engage is currently in pre-alpha stage. There are no public downloads made available yet.

Our plan is to make downloads to the software free of charge.

## Documentation

Akeeba Engage is currently in pre-alpha stage. There is no public documentation yet.

Our plan is to offer documentation to Akeeba Engage through the GitHub repository's Wiki system. 

## Support

Akeeba Engage is currently in pre-alpha stage. No support can be provided yet.

Our plan is to provide _paid support_ for issues regarding installing, configuring and using Akeeba Engage once the software reaches a stable version (1.0.0).

Please note that support is meant to be provided in a way to help you overcome issues with the installation, configuration and use of our software. We cannot do the installation and integration work ourselves, nor can we provide support for customising the frontend of the software including but not limited to template and media overrides. 

Furthermore, kindly note that we cannot advise you on configuring your third party WYSIWYG editor; that's something that is best left to the developer of your WYSIWYG editor for objective reasons. We _can_ help to a certain extent with Joomla's built-in WYSIWYG editor (TinyMCE) only.

Finally, please note that we can only provide limited and generic support for core Joomla features such as text filtering and access control (permissions). We can explain how these feature interact with our software and point you to further information – including our public documentation – but we cannot reconfigure or otherwise do extensive work on your site on your behalf.

## Feature requests

Akeeba Engage is currently in pre-alpha stage. We are not accepting feature requests until the software reaches the first stable version (1.0.0).

### Unacceptable feature requests

There are some kinds of feature requests which will have the predictable outcome of being declined without further consideration:

* _Out of scope features_. Some people may conflate a comments component for a generic support component, a content submission workflow or something equally unrelated to the scope of a comments component. If we determine that your feature request goes outside the fairly narrow scope of Akeeba Engage we will have to respectfully decline it. 
* _Support for commenting content other than Joomla's core content (articles)_. The stated goal of this component is to provide comments to Joomla's articles. We will not accept feature requests about supporting alternative content components (e.g. K2), e-commerce extensions, directory extensions etc. There is a hard technical reason for that; comments are made against a Joomla asset, as recorded in the `#__assets` core table. Moreover, comments are displayed using Joomla's `content` plugins. None of these extensions use both, or either, of these features thus making it technically impossible for Akeeba Engage to support them. If you come up with a valid use case we might consider implementing comments to Joomla article categories, contacts or weblinks. Caveat: the backend interface will be rather clunky and slow if we do that.
* _Display customization features_. You can use template and media overrides to fully customize the display more easily than any number of arbitrary display options can possibly provide. This includes CSS features like print styling; you can override the CSS to provide your own print styling.
* _Attachments_. We considered this feature and decided against it for feature scope and security reasons. If you need attachments you are looking either for a forum component or a help desk / support ticket system component. In some very rare cases you are looking for a social network component. Akeeba Engage is none of the above.
* _Multilingual support / comment merging across articles_. You are commenting on each article. Each article carries its own language. Combining comments from different language versions of a single article or anything to this effect is not going to happen.
* _Duplication of Joomla features_. For example: creating a new user when a comment is filed by a guest; determining who can file a ticket without using Joomla's ACLs; etc. This includes pay-to-comment features. This is best implemented with a third party membership / e-commerce component which adds people purchasing something to a Joomla User Group that allows them to file comments. Moreover, things like including images, videos etc are covered by Joomla's content filtering which can be set up in the Global Configuration; no need for us to reinvent the wheel.

### Ideas for feature requests

Avatars. By default, we support Gravatar. We are interested in implementing other avatar solutions for Joomla. If you know of one and can provide us with a copy of the extension for development purposes we'd be wiling to consider it.

Import comments. Existing sites may already be using a different comment system. We would be open in supporting comments import from select extensions as long as you can provide us with a copy of the extension for development purposes (ideally: a copy of your real world site so we can develop against real world data). It's very unlikely that we'll be interested in or even realistically able to import comments from third party services such as Disqus or Facebook.

Comment emails. We currently have an all-or-nothing approach to sending comments by email. Your use cases may be very different and covered by it. Please let us know what your use cases are so we can better understand them and improve this feature.

### Notes on feature requests

Kindly keep in mind that your unique use case may not be universal. We try to assess feature requests on the basis of what is desirable from the majority of our users and whether implementing it may have an adverse impact on our users or our ability to maintain and support the software.

Accepting a feature request does not constitute a commitment to implementing it. It is possible that a second reading of an accepted feature request while doing the implementation groundwork results in previously undiscovered issues which make us reconsider its acceptance status. Furthermore, due to the finite development resources, the realities of producing a generic, mass distributed extension for Joomla and unforeseen circumstances it may not be possible for us to implement your feature request in a timely manner or at all. 

Finally, we'd like to kindly note that sending a Pull Request is not guaranteed to result in inclusion of your code in our software. Any contributed code becomes our responsibility to maintain and support after merging. Therefore it's only fair that we have the final say on what ends up in our software.

Thank you in advance for your understanding!  

## Collaboration and development

TO BE WRITTEN
