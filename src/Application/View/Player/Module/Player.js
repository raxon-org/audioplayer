//{{RAX}}
import { version } from "/Module/Priya.js";
import { root } from "/Module/Web.js";
import { dialog } from "/Dialog/Module/Dialog.js";
import { player } from "/Application/AudioPlayer/Module/Player.js"
import { taskbar } from "/Application/Desktop/Module/Taskbar.js";
require(
    [
        root() + 'Application/AudioPlayer/Css/Player.css?' + version(),
        root() + 'Dialog/Css/Dialog.css?' + version(),
    ],
    function(){
        player.init("{{$id}}");
        dialog.init("{{$id}}");
        taskbar.active("{{$id}}");
    }
);