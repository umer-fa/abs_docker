let dbMigrationContainer = $("div#dbMigrationContainer");
let dbMigrationElem = $(dbMigrationContainer).find("#dbMigration");

$(function () {
    $("form#dbMigrationGenerator").on("submit", function (e) {
        e.preventDefault();
        e.stopPropagation();

        let form = this;
        let dbGenForm = $(form).serializeObject();
        getDbTableMigration(dbGenForm["table"]);
    });
});

function getDbTableMigration(tableName) {
    xhrCall(rootPath + "/app/dbs?dbTableMigration", {table: tableName}, {type: "get"}, function (result) {
        xhrResult(result);
        if (result.hasOwnProperty("status")) {
            if (result["status"] === true) {
                if (result.hasOwnProperty("migration")) {
                    let migration = result["migration"];
                    let tableName = result["table"];

                    migration = migration.replace(/\n/g, '<br />\n');

                    $(dbMigrationContainer).find(".dbMigrationTableName").text(tableName);
                    $(dbMigrationElem).html(migration);
                    $(dbMigrationContainer).slideDown('slow');
                }
            }
        }
    });
}
