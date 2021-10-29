[![badge](https://img.shields.io/twitter/follow/api_video?style=social)](https://twitter.com/intent/follow?screen_name=api_video)

[![badge](https://img.shields.io/github/stars/apivideo/video-platform-bench?style=social)](https://github.com/apivideo/video-platform-bench)

[![badge](https://img.shields.io/discourse/topics?server=https%3A%2F%2Fcommunity.api.video)](https://community.api.video)

![](https://github.com/apivideo/API_OAS_file/blob/master/apivideo_banner.png)

api.video is an API that encodes on the go to facilitate immediate playback, enhancing viewer streaming experiences across multiple devices and platforms. You can stream live or on-demand online videos within minutes.

# Video Platforms Benchmark

This app aims to benchmark several video/live streaming OTT platforms based on Encoding Time performance and provide also a "Time to Playback" metric.

## What the app does
### VOD benchmark
The app measure 3 metrics when it's possible according to each platform limitations:
* Encoding time for the first quality
* Full encoding time
* Time to Playback

### What platforms are currently supported
* api.video
* AWS(MediaConvert + S3 + CloudFront)
* JW Player
* mux.com
* Vimeo
* Youtube

Because each platform works differently, we've tried to be as accurate and fair as possible when measuring each platform to provide a realistic measurement of each metric.
Some platforms have webhooks that can be listened to be informed of certain encoding-related events, but although this mechanism would have been a preferred choice, we have chosen to base this application on the status endpoint of each API so that all platforms are on par.

### Live benchmark
The app measure one metric:
* Time to Playback
### What platforms are currently supported
* api.video
* AWS(Elemental MediaLive + Elemental MediaPackage + S3 + CloudFront)
* mux.com

## Requirements before Installation
* Create account and credentials for each platform
* (AWS VOD) Create a S3 bucket. [Follow official instruction to create one](https://docs.aws.amazon.com/mediaconvert/latest/ug/set-up-file-locations.html)
* (AWS VOD) Create the IAM role in MediaConvert with full permissions [Follow official instruction to create one](https://docs.aws.amazon.com/mediaconvert/latest/ug/creating-the-iam-role-in-mediaconvert-full.html)
* (AWS VOD) Create a CloudFront distribution with your S3 bucket created as Origin.
* (AWS Live) Edit the file `live-streaming-on-aws-custom.template` at line 34 and enter the CIDR Block of the server that will push the RTMP stream.
* (AWS Live)Upload the edited template to an AWS S3 bucket.

## Installation & Run
1. Clone this repo
2. Set environmental variables
3. Install dependencies with: `composer install`
4. (VOD) Run on localhost `symfony server:start` to start a local web-server
5. Launch the benchmark with command line:
    * VOD: `bin/console bench-video-platform {FILE_TO_UPLOAD_URL}`
    * Live: `bin/console bench-live-platform {FILE_TO_STREAM_URL}`

## Discover api.video
[Learn more on api.video](https://api.video/encoding/)
