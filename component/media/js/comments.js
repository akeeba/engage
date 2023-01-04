/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

"use strict";

if (typeof akeeba == "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.Engage == "undefined")
{
    akeeba.Engage = {};
}

if (typeof akeeba.Engage.Comments == "undefined")
{
    akeeba.Engage.Comments = {};
}

akeeba.Engage.Comments.getElementFromEvent = function (e)
{
    var clickedElement = null;

    if (typeof e.target === "object")
    {
        clickedElement = e.target;
    }
    else if (typeof e.srcElement === "object")
    {
        clickedElement = e.srcElement;
    }

    return clickedElement;
};

akeeba.Engage.Comments.getAssetIdFromEvent = function (e)
{
    var clickedElement = akeeba.Engage.Comments.getElementFromEvent(e);

    if (clickedElement === null)
    {
        return 0;
    }

    return clickedElement.dataset["akengageid"] ?? 0;
};

akeeba.Engage.Comments.onEditButton = function (e)
{
    e.preventDefault();

    /**
     * Construct the edit URL for the comment. The akeeba.Engage.Comments.editURL script option key comes from the
     * components/com_engage\View\Comments\Html.php file.
     */
    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = Joomla.getOptions("akeeba.Engage.Comments.editURL").replace("__ID__", id);
};

akeeba.Engage.Comments.onDeleteButton = function (e)
{
    e.preventDefault();

    var shouldProceed = confirm(Joomla.Text._("COM_ENGAGE_COMMENTS_DELETE_PROMPT"));

    if (!shouldProceed)
    {
        return;
    }

    /**
     * Construct the edit URL for the comment. The akeeba.Engage.Comments.editURL script option key comes from the
     * components/com_engage\View\Comments\Html.php file.
     */
    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = Joomla.getOptions("akeeba.Engage.Comments.deleteURL").replace("__ID__", id);
};

akeeba.Engage.Comments.onPublishButton = function (e)
{
    e.preventDefault();

    /**
     * Construct the edit URL for the comment. The akeeba.Engage.Comments.publishURL script option key comes from the
     * components/com_engage\View\Comments\Html.php file.
     */
    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = Joomla.getOptions("akeeba.Engage.Comments.publishURL").replace("__ID__", id);
};

akeeba.Engage.Comments.onUnpublishButton = function (e)
{
    e.preventDefault();

    /**
     * Construct the edit URL for the comment. The akeeba.Engage.Comments.unpublishURL script option key comes from
     * the components/com_engage\View\Comments\Html.php file.
     */
    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = Joomla.getOptions("akeeba.Engage.Comments.unpublishURL").replace("__ID__", id);
};

akeeba.Engage.Comments.onMarkHamButton = function (e)
{
    e.preventDefault();

    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = Joomla.getOptions("akeeba.Engage.Comments.markhamURL").replace("__ID__", id);
};

akeeba.Engage.Comments.onMarkSpamButton = function (e)
{
    e.preventDefault();

    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = Joomla.getOptions("akeeba.Engage.Comments.markspamURL").replace("__ID__", id);
};

akeeba.Engage.Comments.onMarkPossibleSpamButton = function (e)
{
    e.preventDefault();

    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = Joomla.getOptions("akeeba.Engage.Comments.possiblespamURL").replace("__ID__", id);
};

akeeba.Engage.Comments.onReplyButton = function (e)
{
    e.preventDefault();

    var clickedElement = akeeba.Engage.Comments.getElementFromEvent(e);

    if (clickedElement === null)
    {
        return false;
    }

    akeeba.Engage.Comments.unhideReplyArea();

    var parentId      = (clickedElement.dataset["akengageid"] ?? 0) * 1;
    var inReplyToName = clickedElement.dataset["akengagereplyto"] ?? 0;
    var form          = document.forms["akengageCommentForm"];
    var wrapper       = document.getElementById("akengage-comment-inreplyto-wrapper");

    form["jform[parent_id]"].value = parentId;
    wrapper.classList.add('d-none');

    if (parentId !== 0)
    {
        var inReplyTo       = document.getElementById("akengage-comment-inreplyto-name");
        inReplyTo.innerText = inReplyToName;

        wrapper.classList.remove('d-none');
    }

    document.location.hash = "";
    document.location.hash = "#akengageCommentForm";
};

akeeba.Engage.Comments.onCancelReplyButton = function (e)
{
    e.preventDefault();

    var form      = document.forms["akengageCommentForm"];
    var wrapper   = document.getElementById("akengage-comment-inreplyto-wrapper");
    var inReplyTo = document.getElementById("akengage-comment-inreplyto-name");

    form["jform[parent_id]"].value = 0;
    wrapper.style.display          = "none";
    inReplyTo.innerText            = "";
};

akeeba.Engage.Comments.saveCommenterInfo = function (e)
{
    if (typeof window.localStorage === "undefined")
    {
        return;
    }

    var storedName  = window.localStorage.getItem("engageGuestComment_name");
    var storedEmail = window.localStorage.getItem("engageGuestComment_email");

    window.localStorage.removeItem("engageGuestComment_name");
    window.localStorage.removeItem("engageGuestComment_email");

    var elSaveInfo = document.getElementById("akengage-comment-form-saveinfo");

    if (!(elSaveInfo instanceof Element) || !elSaveInfo.checked)
    {
        return;
    }

    var elName  = document.getElementById("akengage-comment-form-name");
    var elEmail = document.getElementById("akengage-comment-form-email");

    if (!(elName instanceof Element) || !(elEmail instanceof Element))
    {
        return;
    }

    var myName  = elName.value;
    var myEmail = elEmail.value;

    if ((storedName === null) || (storedEmail === null))
    {
        storedName  = myName;
        storedEmail = myEmail;
    }

    if ((myName !== storedName) || (myEmail !== storedEmail) || (myName === "") || (myEmail === ""))
    {
        window.localStorage.removeItem("engageGuestComment_name");
        window.localStorage.removeItem("engageGuestComment_email");

        return;
    }

    window.localStorage.setItem("engageGuestComment_name", myName);
    window.localStorage.setItem("engageGuestComment_email", myEmail);
};

akeeba.Engage.Comments.loadCommenterInfo = function ()
{
    if (typeof window.localStorage === "undefined")
    {
        return;
    }

    var storedName  = window.localStorage.getItem("engageGuestComment_name");
    var storedEmail = window.localStorage.getItem("engageGuestComment_email");

    if ((storedName === "") || (storedEmail === "") || (storedName === null) || (storedEmail === null))
    {
        storedName  = "";
        storedEmail = "";
    }

    var elName  = document.getElementById("akengage-comment-form-name");
    var elEmail = document.getElementById("akengage-comment-form-email");

    if (!(elName instanceof Element) || !(elEmail instanceof Element))
    {
        return;
    }

    if ((elName.value == "") && (elEmail.value == ""))
    {
        elName.value  = storedName;
        elEmail.value = storedEmail;
    }

    if ((storedName === "") || (storedEmail === ""))
    {
        return;
    }

    var elSaveInfo = document.getElementById("akengage-comment-form-saveinfo");

    if (elSaveInfo instanceof Element)
    {
        elSaveInfo.setAttribute("checked", "checked");
    }
};

akeeba.Engage.Comments.onHiderButton = function (e)
{
    akeeba.Engage.Comments.unhideReplyArea();
};

akeeba.Engage.Comments.unhideReplyArea = function ()
{
    var elFormArea = document.getElementById("akengageCommentForm");
    var elHider    = document.getElementById("akengage-comment-hider");

    elFormArea.classList.remove('d-none');
    elFormArea.style.display = "";

    if (elHider)
    {
        elHider.classList.add('d-none');
        elHider.style.display = "none";
    }
};

[].slice.call(document.querySelectorAll("button.akengage-comment-edit-btn")).forEach(function (el)
{
    el.addEventListener("click", akeeba.Engage.Comments.onEditButton);
});

[].slice.call(document.querySelectorAll("button.akengage-comment-delete-btn")).forEach(function (el)
{
    el.addEventListener("click", akeeba.Engage.Comments.onDeleteButton);
});

[].slice.call(document.querySelectorAll("button.akengage-comment-reply-btn")).forEach(function (el)
{
    el.addEventListener("click", akeeba.Engage.Comments.onReplyButton);
});

[].slice.call(document.querySelectorAll("button.akengage-comment-unpublish-btn")).forEach(function (el)
{
    el.addEventListener("click", akeeba.Engage.Comments.onUnpublishButton);
});

[].slice.call(document.querySelectorAll("button.akengage-comment-publish-btn")).forEach(function (el)
{
    el.addEventListener("click", akeeba.Engage.Comments.onPublishButton);
});

[].slice.call(document.querySelectorAll("button.akengage-comment-markham-btn")).forEach(function (el)
{
    el.addEventListener("click", akeeba.Engage.Comments.onMarkHamButton);
});

[].slice.call(document.querySelectorAll("button.akengage-comment-markspam-btn")).forEach(function (el)
{
    el.addEventListener("click", akeeba.Engage.Comments.onMarkSpamButton);
});

[].slice.call(document.querySelectorAll("button.akengage-comment-possiblespam-btn")).forEach(function (el)
{
    el.addEventListener("click", akeeba.Engage.Comments.onMarkPossibleSpamButton);
});

var inReplyTo = document.getElementById("akengage-comment-inreplyto-cancel");
if (inReplyTo)
{
    inReplyTo.addEventListener("click", akeeba.Engage.Comments.onCancelReplyButton);
}

var commentForm = document.getElementById("akengageCommentForm");
if (commentForm)
{
    commentForm.addEventListener("submit", akeeba.Engage.Comments.saveCommenterInfo);
}

var elHider = document.getElementById("akengage-comment-hider-button");
if (elHider)
{
    elHider.addEventListener("click", akeeba.Engage.Comments.onHiderButton);
}

akeeba.Engage.Comments.loadCommenterInfo();