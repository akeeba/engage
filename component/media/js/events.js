/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

"use strict";

window.addEventListener('DOMContentLoaded', function() {
    var onEventSubmit = function (event)
    {
        event.preventDefault();

        var elChangedElement = event.currentTarget;
        var targetString     = elChangedElement.dataset.engagesubmittarget ?? "";
        var elTarget         = document.forms.adminForm ? document.forms.adminForm : null;

        if (targetString !== "")
        {
            elTarget = document.getElementById(targetString);
        }

        if (!elTarget)
        {
            return true;
        }

        elTarget.submit();

        return false;
    };

    var onClickConfirm = function (event)
    {
        event.preventDefault();

        var elChangedElement  = event.currentTarget;
        var confirmLangString = elChangedElement.dataset.engageconfirmmessage ?? "";

        if (confirmLangString === "")
        {
            return true;
        }

        return confirm(Joomla.Text._(confirmLangString));
    };

    [].slice.call(document.querySelectorAll('.engageCommonEventsOnChangeSubmit')).forEach(function(el) {
        el.addEventListener('change', onEventSubmit);
    });

    [].slice.call(document.querySelectorAll('.engageCommonEventsOnClickSubmit')).forEach(function(el) {
        el.addEventListener('click', onEventSubmit);
    });

    [].slice.call(document.querySelectorAll('.engageCommonEventsOnBlurSubmit')).forEach(function(el) {
        el.addEventListener('blur', onEventSubmit);
    });

    [].slice.call(document.querySelectorAll('.engageCommonEventsOnClickConfirm')).forEach(function(el) {
        el.addEventListener('click', onClickConfirm);
    });
});
