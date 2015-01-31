# DeRouting
-----------

## SETUP DEV ENVIRONMENT FOR DE-ROUTING APP
follow this guide: http://cordova.apache.org/docs/en/3.5.0/guide_platforms_android_index.md.html

### install homebrew
ruby -e "$(curl -fsSL https://raw.github.com/mxcl/homebrew/go/install)"
brew update

### install ant
brew install ant

### download and install node.js
http://nodejs.org/

### install cordova
sudo npm install -g cordova

### download and install android sdk
http://developer.android.com/sdk/installing/index.html?pkg=adt
start eclipse -> SDK Manager install packages: android 2.3 (api 10)


## Build Commands

1. cordova build android
2. cordova run android (to run it on the android phone)


## MISC CHANGES

file transder plugin:
----------------------
this code stops the tansfer from failing:
options.chunkedMode = true;
options.headers = {Connection: "close"};

media capture plugin:
---------------------
change function:
private String getTempDirectoryPath() {
    File cache = null;

    // Use internal storage
    //cache = cordova.getActivity().getCacheDir();
    cache = cordova.getActivity().getExternalCacheDir();
    

    // Create the cache directory if it doesn't exist
    cache.mkdirs();
    return cache.getAbsolutePath();
}
