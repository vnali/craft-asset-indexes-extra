(function () {
    $(".convertTo select").change(function () {
        field = $(this).closest('tr').data('id');
        container = $(this).closest('tr').find('.container select').val();
        if (this.value == '') {
            var selectize = $('tr[data-id="' + field + '"] .selectfield select').get(0).selectize;
            selectize.clear();
            selectize.clearOptions();
        } else {
            selectField(field, this.value, container);
        }
    });

    $("#itemType").on("change", function () {
        subItem();
    });

    $(".triggerContainer select").on("change", function () {
        item = $("#itemType").val();
        itemId = $(this).val();
        containers(item, itemId);
    });

    subItem();

})();

function subItem() {
    item = $("#itemType").val();
    if (item == 'Entry' || item == 'Product' || item == 'Digital Product') {
        $('#item-mapping').css('display', 'block');
    } else {
        $('#item-mapping').css('display', 'none');
    }
    if (item == 'Entry') {
        $('#subproduct').css('display', 'none');
        $('#subdigitalproduct').css('display', 'none');
        $('#subentry').css('display', 'block');
        itemId = $("#entryType").val();
        containers('Entry', itemId);
    } else if (item == 'Product') {
        $('#subentry').css('display', 'none');
        $('#subdigitalproduct').css('display', 'none');
        $('#subproduct').css('display', 'block');
        itemId = $("#productType").val();
        containers('Product', itemId);
    } else if (item == 'Digital Product') {
        $('#subentry').css('display', 'none');
        $('#subproduct').css('display', 'none');
        $('#subdigitalproduct').css('display', 'block');
        itemId = $("#digitalProductType").val();
        containers('Digital Product', itemId);
    } else if (item == '') {
        $('#subentry').css('display', 'none');
        $('#subproduct').css('display', 'none');
        $('#subdigitalproduct').css('display', 'none');
    }
}

function containers(item, itemId) {
    if (!itemId) {
        return;
    }
    var data = {
        'item': item,
        'itemId': itemId,
        'onlyContainer': false
    };
    var selectize = $('tr[data-id="mainAsset"] .container select').get(0).selectize;

    if (selectize) {
        $.ajax({
            method: "GET",
            url: Craft.getUrl("asset-indexes-extra/default/containers" + "?t=" + new Date().getTime()),
            data: data,
            dataType: 'json',
            success: function (data) {
                selectize.clear();
                selectize.clearOptions();
                $.each(data, function (i, item) {
                    selectize.addOption({
                        value: item.value,
                        text: item.label
                    });
                });
                selectize.addItem('');
            }
        });
    }
}

function selectField(field, convertTo, container, selected = '') {

    var item = $('#itemType').val();
    var itemId = null;
    if (item == 'Entry') {
        var itemId = $('#entryType').val();
    } else if (item == 'Product') {
        var itemId = $('#productType').val();
    } else if (item == 'Digital Product') {
        var itemId = $('#digitalProductType').val();
    }

    if (!itemId) {
        return;
    }

    var data = {
        'convertTo': convertTo,
        'fieldContainer': container,
        'limitFieldsToLayout': 'true',
        'item': item,
        'itemId': itemId
    };
    var selectize = $('tr[data-id="' + field + '"] .selectfield select').get(0).selectize;

    $.ajax({
        method: "GET",
        url: Craft.getUrl("asset-indexes-extra/default/fields-filter" + "?t=" + new Date().getTime()),
        data: data,
        dataType: 'json',
        success: function (data) {
            selectize.clear();
            selectize.clearOptions();
            $.each(data, function (i, item) {
                selectize.addOption({
                    value: item.value,
                    text: item.label
                });
            });
            selectize.addItem(selected);
        }
    });
}