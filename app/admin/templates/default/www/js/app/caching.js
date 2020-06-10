$(function () {
    $("[data-del-cached]").on("click", function () {
        let key = $(this).attr("data-del-cached");
        if (key && key.length) {
            deleteCacheKey(key);
        }
    });
});

function deleteCacheKey(key) {
    xhrCall(rootPath + '/app/cache?cached', {key: key}, {type: "delete"}, function (result) {
        xhrResult(result);
        if (result.hasOwnProperty("status") && result["status"] === true) {
            let cachedItemRow = $("tr#cached_item_" + key);
            $(cachedItemRow).remove();
        }
    });
}
