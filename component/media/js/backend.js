/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
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

if (typeof akeeba.Engage.AdminComments == "undefined")
{
    akeeba.Engage.AdminComments = {};
}

akeeba.Engage.AdminComments.onReset = function (event)
{
    event.preventDefault();

    document.getElementById("filter_body").value         = "";
    document.getElementById("filter_asset_title").value  = "";
    document.getElementById("filter_filter_email").value = "";
    document.getElementById("filter_ip").value           = "";
    document.getElementById("filter_asset_id").value     = "";
    document.getElementById("filter_published").value    = "";

    akeeba.System.submitForm("browse", document.getElementById("adminForm"));

    return false;
}

akeeba.System.documentReady(function ()
{
    akeeba.System.addEventListener("comEngageResetFilters", "click", akeeba.Engage.AdminComments.onReset);

});