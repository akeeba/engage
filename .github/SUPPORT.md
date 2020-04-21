## Support philosophy

The software is provided free of charge. Personalised support and custom 
development for it is not. 

We will be happy to discuss bug reports and 
feature requests free of charge and only through GitHub issues in the 
relevant repository as long as the conditions outlined in this document are
met.

Please remember that we only provide limited free of charge support for bug
reports and feature requests in the context of furthering the interests of
the target audience of our software _in general_, not just specific 
individuals. Think of it as tending to the community garden everyone can use
and enjoy, not fencing parts of it off so that only specific people are 
allowed to use it.

## Be respectful

First and foremost, be respectful. All interactions with community members,
contributors and maintainers are subject to the Code of Conduct.

## Free Software does not mean free-of-charge services

Kindly note that the word “Free” in the Free and Open Source Software (FOSS)
designation of the software refers to _freedom of choice_, not lack of
cost, as explained [by the Free Software Foundation itself](https://www.gnu.org/philosophy/selling.en.html).
If you don't understand why that link is important: it's by the same people
who wrote the GNU General Public License our software is licensed under and
who founded the Free Software movement itself.

Using our free of charge, FOSS software does not create any kind of business
or other relationship between us, does not grant you any privileges and does 
not mean that we are obliged to offer any service level whatsoever free of 
charge. 

You are _free to choose_ whether you want to use our FOSS software and the 
resources we make available to you free of charge. You are also free to 
choose whether to use the limited free of charge support we offer or not.
You are, however, not entitled to any privileges or special treatment just 
by merit of using our software. 

## Policy on bug reports

Bug reports are only considered for free of charge resolution by the 
maintainers when the issue is reproducible given your instructions on a site
and hosting _different than yours_. 

If your issue can only be reproduced on your site and / or hosting please try
to isolate what is causing the problem and give us more accurate instructions
which helps us reproduce the issue. Only then can we investigate whether it's
a legitimate bug with our software or a problem with your site or hosting 
environment. 

If your issue does not meet this reproducibility criteria, if it's unrelated to
our software or is something already documented we will probably close your 
GitHub issue without further commentary.

## Policy on feature requests

We will be happy to discuss and consider feature requests with a justified reasoning behind them. At the very least we expect you to tell us what is the problem you are trying to solve, which alternatives you have considered and why you believe that your proposal is a good fit for our community in general (i.e. show us that you are not assuming that you are fully representing the entire  community).

Please do keep in mind that your requested feature change or addition is something which has to be developed, supported, maintained and defended by the maintainers of the project in perpetuity. As a result only feature changes or additions which make sense for the overall community will be considered. Moreover, the decision on whether a feature will be implemented at all, when it will happen and how it will happen lies exclusively with the maintainers and no promises are made on a timeframe.

Accepting a feature request does not constitute a commitment to an implementation timeframe or even a commitment that it will be implemented at all. It is possible that a second reading of an accepted feature request while doing the implementation groundwork results in previously undiscovered issues which make us reconsider its acceptance status. Furthermore, due to the finite development resources, the realities of producing a generic, mass distributed extension for Joomla and unforeseen circumstances it may not be possible for us to implement your feature request in a timely manner or at all.

Finally, we'd like to kindly note that sending a Pull Request is not guaranteed to result in inclusion of your code in our software. As noted, any contributed code becomes our responsibility to maintain and support after merging. Therefore it's only fair that we have the final say on what ends up in our software.

Thank you in advance for your understanding!  

### Unacceptable feature requests

There are some kinds of feature requests which will have the predictable outcome of being declined without further consideration:

* _Out of scope features_. Some people may conflate a comments component for a generic support component, a content submission workflow or something equally unrelated to the scope of a comments component. If we determine that your feature request goes outside the fairly narrow scope of Akeeba Engage (user submitted comments in Joomla core content articles) we will have to respectfully decline it. 
* _Support for commenting content other than Joomla's core content (articles)_. The stated goal of this component is to provide comments to Joomla's articles. We will not accept feature requests about supporting alternative content components (e.g. K2), e-commerce extensions, directory extensions etc. It wouldn't be possible without throwing away and rewriting the component which we're not willing to do.
* _Display customization features_. You can use template and media overrides to fully customize the display more easily than any number of arbitrary display options could provide. This includes CSS features like print styling; you can override the CSS to provide your own print styling.
* _Attachments_. We considered this feature and decided against it for feature scope and security reasons. If you need attachments you are looking either for a forum component or a help desk / support ticket system component. In some very rare cases you are looking for a social network component. Akeeba Engage is none of the above (see: Out of scope features).
* _Multilingual support / comment merging across articles_. You are commenting on each article. Each article carries its own language. Combining comments from different language versions of a single article or anything to this effect is not going to happen.
* _Duplication of Joomla features_. For example: creating a new user when a comment is submitted by a guest; determining who can file a ticket without using Joomla's permissions; etc. This includes pay-to-comment features. This is best implemented with a third party membership / e-commerce component which adds people purchasing something to a Joomla User Group that allows them to file comments. Moreover, things like including images, videos etc are covered by Joomla's content filtering which can be set up in the Global Configuration; no need for us to reinvent the wheel.
* _Per-article / per-category permissions_. Unfortunately, this is technically impossible. It has to do with the way Joomla uses the `#__assets` table to cascade permissions. However, this is possible for the ONE component that "owns" the content. In the case of articles that's `com_content` (the Joomla articles component). Comments are "owned" by Akeeba Engage (`com_engage`), a different component. There is no way to add Akeeba Engage's permissions into com_content without modifying Joomla core files which is not sustainable, advisable or even permitted by the Joomla Extensions Directory (it's a core hack). Therefore comment permissions HAVE to be global. Any workaround to that is extremely slow, making it impractical. This is a limitation of the Joomla permissions system we've discussed with core contributors as early as April 2010, before the release of Joomla 1.6.

### Ideas for feature requests

Avatars. By default, we support Gravatar. We are interested in implementing other avatar solutions for Joomla. If you know of one and can provide us with a copy of the extension for development purposes we'd be wiling to consider it.

Import comments. Existing sites may already be using a different comment system. We would be open in supporting comments import from select extensions as long as you can provide us with a copy of the extension for development purposes (ideally: a copy of your real world site so we can develop against real world data). It's very unlikely that we'll be interested in or even realistically able to import comments from third party services such as Disqus or Facebook.

Comment emails. We currently have an all-or-nothing approach to sending comments by email. Your use cases may be very different and covered by it. Please let us know what your use cases are so we can better understand them and improve this feature.

## Paid support

We encourage you to read our documentation Wiki and try to figure out a solution
to your issue yourself. Our software is typically reasonably simple to use. 
Whenever there are tricky conditions such as the need to configure a third party
service for use with our software or the need to do template overrides or other
Joomla or hosting specific configuration to use our software we do document it.

If you are unable to find a solution just by using the documentation we encourage
you to seek peer support in the Joomla Forum or your local Joomla User Group,
either in person or through their Facebook page / local forum. The chances are
someone else has either run into the same issue or understands how to fix it.

If you still cannot find a solution to your issue or do not have time to go 
through the free-of-charge, community-driven support outlined above you can 
always hire the maintainers to provide you with personalised support for a 
very reasonable fee.

If you submit a bug report or other issue that is not a legitimate bug report 
per the criteria outlined above the maintainers will remind you of this policy 
and let you decide whether you want to close your issue or continue with paid
support.

## Paid development

We do not accept paid development projects for this software due to lack of
time.

## General information about paid services

We are not obliged to accept any request for paid support or development. Our 
acceptance is subject to availability and our assessment of the objective 
feasibility and legality of what is being asked.

Please bear in mind that the maintainers will not reject bug reports or feature
requests just so that you can spend money with us. If your starting position
is one of mistrust towards us please do not submit a GitHub issue to save both
of us a lot of time and frustration.

Paid services do come with an invoice, no matter if you are a business or a
natural person. We do not accept cash “donations” or other non-monetary 
“gifts” in exchange for services.