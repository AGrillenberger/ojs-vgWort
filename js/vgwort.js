/**
 * @file js/vgwort.js
 *
 * Copyright (c) 2022 Heidelberg University Library
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Code for disabling radio buttons in the VG Wort form.
 */

pkp.eventBus.$on('form-success', function (formId, response) {
    document.getElementById("vgwortform-vgWort::pixeltag::assign-description").innerHTML = "Status: " + vgWortPixeltagStatusLabels[response['vgWort::pixeltag::status']];
    document.querySelector("input[name='vgWort::pixeltag::assign'][value='true']").disabled = response['vgWort::pixeltag::assign'];
    document.querySelector("input[name='vgWort::pixeltag::assign'][value='false']").disabled = !response['vgWort::pixeltag::assign'];
});

