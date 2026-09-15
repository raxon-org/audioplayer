{{$register = Package.Raxon.Audioplayer:Init:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Audioplayer:Import:role.system()}}
{{$flags = flags()}}
{{$options = options()}}
{{Package.Raxon.Audioplayer:Setup:install($flags, $options)}}
{{/if}}