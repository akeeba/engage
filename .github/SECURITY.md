# Security Policy

## Supported Versions

This paragraph applies only in the context of security. The term "support" refers to which versions of our software and its operating environment we are willing to consider when tracing and resolving security issues. It does NOT refer to end user support.

Only the latest `master` and `development` branches are supported with security updates. The `master` branch represents the last published version, whereas the `development` branch represents the upcoming version.

We only support the latest published, stable Joomla version in the 3.x and 4.x branch. We do not support alphas, betas or release candidates (testing releases). If a security issue only occurs with a testing release we will consider it but we cannot promise a rapid resolution.

## Reporting a Vulnerability

Please **DO NOT** file a GitHub issue about security issues. GitHub issues are public. Filing an issue about a security issue puts all users, you included, in immediate danger.

Please use our [contact page](https://www.akeeba.com/contact-us.html) to send us a private notification about the security issue. We strongly recommend using GPG to encrypt your email. You can find the lead developer's public GPG key at https://keybase.io/nikosdion

Please include instructions to reproduce the security issue. Better yet, please include Proof Of Concept code if applicable.

## Expected timeframe

TL;DR - Typical timeframe:

* T-2 to 10 days: we receive your notification about a security issue.
* T-0: we acknowledge the security issue and start working on it.
* T+30 days: we release a patched version and you can make an announcement without details or POC code.
* T+90 days: you can talk about it in public without any restriction, release POC code etc.

Security issues are typically processed within 2 business days with the exception of vacations, event attendance or family emergencies. We will contact you to let you know of our evaluation of the security issue and possibly request more information.

As soon as we acknowledge an issue we typically ask for 30 days to come up with a solution and release a fixed version. We kindly request that no announcement about the security issue is made in public during that period of time. You will be credited with the discovery of the vulnerability in our release notes and our release announcements (if any). You may not receive a notification about the release of the new version but we encourage you to monitor our automated release update feed.

Once this time period elapses and / or we have released a fixed version you are free to make a public announcement about your vulnerability as long as you do not give away specifics or proof-of-concept code. We kindly request that you give an additional 60 days for our users to have a chance to update their software.

After the additional 60 days you are more than welcome to release detailed information and proof-of-concept code, as well as make any public announcements about the vulnerability you  discovered.

## Bug bounties

We are a tiny company that barely supports three families. Unfortunately, this means that we do not have a budget for bug bounties.

If you report a security issue you will have our gratitude and an honorable, public mention of your findings in our release notes. You also get the warm, fuzzy feeling that you helped secure thousands of sites (and the bragging rights which translate to a bigger paycheck on your next security-related job). Everybody wins!

## Please make sure your issue makes sense

We appreciate the time and effort expended by legitimate security researchers in identifying and reporting security issues.

There are also many people who try to get started with security research (good for you!) or think that this field is an excuse to make easy money (don't give in to the Dark Side!). Sometimes the reports we get from these people are problematic and ultimately unusable. Sometimes we get legitimate researchers who are probably fed up with developers taking more than their sweet time to respond and play extreme hardball, putting everyone at risk. Here are some examples of what not to do when filing a security issue, please:

* Demanding a fix in an unreasonable amount of time (under 30 days). We literally drop everything else and start working on fixing a security issue as soon as we get reproducible information about it. We only have two software engineers who also do end user support. If your report finds us in the middle of a release cycle we need to not only fix the security issue you reported but also figure out how we can best handle the release cycle (e.g. backport the fix to the previous release and issue a security fix, finalize the current version or something in between). That's stressful as it is. Putting a ticking time bomb under our chair won't make us work any faster or longer than the 10-12 hour days we already do. In the end of the day forcing us to release an untested version that's likely to frustrate people (and make some of them downgrade back to the vulnerable version) doesn't help anyone and beats the purpose of reporting a security issue.

* Demanding you release full details and / or POC code as soon as we release an updated version. Our security advisories at the time of release are _intentionally_ vague to instill enough urgency for people to upgrade but not give enough information to bad actors to identify and exploit the issue while people evaluate what needs to be done to upgrade our software (remember that some sites need to go through an approval process for updates). Undercutting our efforts by immediately releasing full information puts everybody at risk and beats the purpose of reporting a security issue privately. You might just as well be a black hat and publish a zero day.

* "I can hack myself". If you are a logged in as a top tier, privileged user, e.g. Administrator or Super User, you can hack your own site. Can your issue be plausibly exploited by XSS, phishing or another method which doesn't require the attacker to be logged in to the site themselves? If not, your issue is invalid by definition.

* Misconfiguration in Joomla permissions or text filters can lead to a site compromise. That's tautological and there's a caveat that comes with it. If your vulnerability assessment only worked because the ONLY user on your test site was the Super User created by default when installing Joomla and / or you misconfigured Joomla on purpose your assessment is invalid (see "I can hack myself"). Try using a simple Registered user and the default Joomla permissions and text filters to reproduce your issue.

* A report from a security scanner service or software with no further commentary. It's lazy, fairly obvious and tells us that you don't know (or understand) if it's a false positive, how you can actually reproduce this issue, let alone use it to attack a site or its users. Basically, you didn't do any work, your report is invalid and it makes you look bad. Try using that report as a starting point to do more research.

* A demand of payment to give us information on an alleged security issue OR to withhold a public announcement of it. We do not negotiate with extortionists. Instead, your name and email will be published with a note that you are trying to extort developers. 

* Reporting an issue in a language other than English, being vulgar or otherwise showing poor communication skills. Between both software engineers we speak English, Greek and Italian. We ask you to file security issues in English because it's the lingua franca of IT and we are both fluent in it. We're also humans, not punching bags. We want to produce secure, usable software for everyone. Please respect the fact that we're on the same side. Thank you. 