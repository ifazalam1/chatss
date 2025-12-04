@if(!empty($siteSettings->google_analytics_code))
    {!! base64_decode($siteSettings->google_analytics_code) !!}
@endif

@if(!empty($siteSettings->facebook_pixel_code))
    {!! base64_decode($siteSettings->facebook_pixel_code) !!}
@endif
