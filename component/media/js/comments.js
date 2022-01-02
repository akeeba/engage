/*!
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

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

    return akeeba.System.data.get(clickedElement, "akengageid", "0");
};

akeeba.Engage.Comments.onEditButton = function (e)
{
    e.preventDefault();

    /**
     * Construct the edit URL for the comment. The akeeba.Engage.Comments.editURL script option key comes from the
     * components/com_engage\View\Comments\Html.php file.
     */
    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = akeeba.System.getOptions("akeeba.Engage.Comments.editURL") + encodeURIComponent(id)
        + "&" + akeeba.System.getOptions("csrf.token") + "=1&returnurl=" +
        encodeURIComponent(akeeba.System.getOptions("akeeba.Engage.Comments.returnURL", "index.php"));
};

akeeba.Engage.Comments.onDeleteButton = function (e)
{
    e.preventDefault();

    var shouldProceed = confirm(akeeba.System.Text._("COM_ENGAGE_COMMENTS_DELETE_PROMPT"));

    if (!shouldProceed)
    {
        return;
    }

    /**
     * Construct the edit URL for the comment. The akeeba.Engage.Comments.editURL script option key comes from the
     * components/com_engage\View\Comments\Html.php file.
     */
    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = akeeba.System.getOptions("akeeba.Engage.Comments.deleteURL") + encodeURIComponent(id)
        + "&" + akeeba.System.getOptions("csrf.token") + "=1&returnurl=" +
        encodeURIComponent(akeeba.System.getOptions("akeeba.Engage.Comments.returnURL", "index.php"));
};

akeeba.Engage.Comments.onPublishButton = function (e)
{
    e.preventDefault();

    /**
     * Construct the edit URL for the comment. The akeeba.Engage.Comments.publishURL script option key comes from the
     * components/com_engage\View\Comments\Html.php file.
     */
    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = akeeba.System.getOptions("akeeba.Engage.Comments.publishURL") + encodeURIComponent(id)
        + "&" + akeeba.System.getOptions("csrf.token") + "=1&returnurl=" +
        encodeURIComponent(akeeba.System.getOptions("akeeba.Engage.Comments.returnURL", "index.php"));
};

akeeba.Engage.Comments.onUnpublishButton = function (e)
{
    e.preventDefault();

    /**
     * Construct the edit URL for the comment. The akeeba.Engage.Comments.unpublishURL script option key comes from
     * the components/com_engage\View\Comments\Html.php file.
     */
    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = akeeba.System.getOptions("akeeba.Engage.Comments.unpublishURL") + encodeURIComponent(id)
        + "&" + akeeba.System.getOptions("csrf.token") + "=1&returnurl=" +
        encodeURIComponent(akeeba.System.getOptions("akeeba.Engage.Comments.returnURL", "index.php"));
};

akeeba.Engage.Comments.onMarkHamButton = function (e)
{
    e.preventDefault();

    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = akeeba.System.getOptions("akeeba.Engage.Comments.markhamURL") + encodeURIComponent(id)
        + "&" + akeeba.System.getOptions("csrf.token") + "=1&returnurl=" +
        encodeURIComponent(akeeba.System.getOptions("akeeba.Engage.Comments.returnURL", "index.php"));
};

akeeba.Engage.Comments.onMarkSpamButton = function (e)
{
    e.preventDefault();

    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = akeeba.System.getOptions("akeeba.Engage.Comments.markspamURL") + encodeURIComponent(id)
        + "&" + akeeba.System.getOptions("csrf.token") + "=1&returnurl=" +
        encodeURIComponent(akeeba.System.getOptions("akeeba.Engage.Comments.returnURL", "index.php"));
};

akeeba.Engage.Comments.onMarkPossibleSpamButton = function (e)
{
    e.preventDefault();

    var id = akeeba.Engage.Comments.getAssetIdFromEvent(e);

    window.location = akeeba.System.getOptions("akeeba.Engage.Comments.possiblespamURL") + encodeURIComponent(id)
        + "&" + akeeba.System.getOptions("csrf.token") + "=1&returnurl=" +
        encodeURIComponent(akeeba.System.getOptions("akeeba.Engage.Comments.returnURL", "index.php"));
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

    var parentId      = akeeba.System.data.get(clickedElement, "akengageid", 0) * 1;
    var inReplyToName = akeeba.System.data.get(clickedElement, "akengagereplyto", 0);
    var form          = document.forms["akengageCommentForm"];
    var wrapper       = document.getElementById("akengage-comment-inreplyto-wrapper");

    form.parent_id.value  = parentId;
    wrapper.style.display = "none";

    if (parentId !== 0)
    {
        var inReplyTo       = document.getElementById("akengage-comment-inreplyto-name");
        inReplyTo.innerText = inReplyToName;

        wrapper.style.display = "block";
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

    form.parent_id.value  = 0;
    wrapper.style.display = "none";
    inReplyTo.innerText   = "";
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
}

akeeba.Engage.Comments.unhideReplyArea = function()
{
    var elFormArea = document.getElementById('akengage-comment-form');
    var elHider = document.getElementById('akengage-comment-hider');

    elFormArea.style.display = '';

    if (elHider) {
        elHider.style.display = 'none';
    }
}

akeeba.Loader.add(['akeeba.System'], function ()
{
    akeeba.System.iterateNodes("button.akengage-comment-edit-btn", function (elButton)
    {
        akeeba.System.addEventListener(elButton, "click", akeeba.Engage.Comments.onEditButton);
    });

    akeeba.System.iterateNodes("button.akengage-comment-delete-btn", function (elButton)
    {
        akeeba.System.addEventListener(elButton, "click", akeeba.Engage.Comments.onDeleteButton);
    });

    akeeba.System.iterateNodes("button.akengage-comment-reply-btn", function (elButton)
    {
        akeeba.System.addEventListener(elButton, "click", akeeba.Engage.Comments.onReplyButton);
    });

    akeeba.System.iterateNodes("button.akengage-comment-unpublish-btn", function (elButton)
    {
        akeeba.System.addEventListener(elButton, "click", akeeba.Engage.Comments.onUnpublishButton);
    });

    akeeba.System.iterateNodes("button.akengage-comment-publish-btn", function (elButton)
    {
        akeeba.System.addEventListener(elButton, "click", akeeba.Engage.Comments.onPublishButton);
    });

    akeeba.System.iterateNodes("button.akengage-comment-markham-btn", function (elButton)
    {
        akeeba.System.addEventListener(elButton, "click", akeeba.Engage.Comments.onMarkHamButton);
    });

    akeeba.System.iterateNodes("button.akengage-comment-markspam-btn", function (elButton)
    {
        akeeba.System.addEventListener(elButton, "click", akeeba.Engage.Comments.onMarkSpamButton);
    });

    akeeba.System.iterateNodes("button.akengage-comment-possiblespam-btn", function (elButton)
    {
        akeeba.System.addEventListener(elButton, "click", akeeba.Engage.Comments.onMarkPossibleSpamButton);
    });

    akeeba.System.addEventListener(
        "akengage-comment-inreplyto-cancel", "click", akeeba.Engage.Comments.onCancelReplyButton);

    akeeba.System.addEventListener(
        "akengageCommentForm", "submit", akeeba.Engage.Comments.saveCommenterInfo);

    akeeba.System.addEventListener(
        "akengage-comment-hider-button", "click", akeeba.Engage.Comments.onHiderButton);

    akeeba.Engage.Comments.loadCommenterInfo();
});