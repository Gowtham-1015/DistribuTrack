const { app, BrowserWindow, Menu } = require("electron");
const { spawn } = require("child_process");
const path = require("path");

let phpServer;

function startPHPServer() {

    // Bundled PHP location
    const phpPath = app.isPackaged
        ? path.join(process.resourcesPath, "php", "php.exe")
        : path.join(__dirname, "php", "php.exe");

    // PHP configuration file
    const phpIni = app.isPackaged
        ? path.join(process.resourcesPath, "php", "php.ini")
        : path.join(__dirname, "php", "php.ini");

    // PHP project location
    const projectPath = app.isPackaged
        ? path.join(process.resourcesPath, "project")
        : path.join(__dirname, "project");


    console.log("PHP Path:", phpPath);
    console.log("Project Path:", projectPath);


    phpServer = spawn(phpPath, [
        "-c",
        phpIni,
        "-S",
        "127.0.0.1:9000",
        "-t",
        projectPath
    ], {
        cwd: projectPath,
        windowsHide: true
    });


    phpServer.stdout.on("data", (data) => {
        console.log("PHP:", data.toString());
    });


    phpServer.stderr.on("data", (data) => {
        console.error("PHP Error:", data.toString());
    });


    phpServer.on("error", (err) => {
        console.error("Failed to start PHP:", err);
    });


    phpServer.on("close", (code) => {
        console.log("PHP stopped with code:", code);
    });
}


function createWindow() {

    const win = new BrowserWindow({

        title: "DistribuTrack",

        width: 1400,
        height: 900,

        minWidth: 1200,
        minHeight: 700,

        autoHideMenuBar: true,

        icon: path.join(__dirname, "icon.ico"),

        webPreferences: {
            contextIsolation: true,
            nodeIntegration: false
        }

    });


    win.maximize();


    setTimeout(() => {
        win.loadURL("http://127.0.0.1:9000");
    }, 1500);

}



app.whenReady().then(() => {

    Menu.setApplicationMenu(null);

    startPHPServer();

    createWindow();

});



app.on("window-all-closed", () => {

    if (phpServer) {
        phpServer.kill();
    }

    if (process.platform !== "darwin") {
        app.quit();
    }

});