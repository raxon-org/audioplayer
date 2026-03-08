import { taskbar } from "/Application/Desktop/Module/Taskbar.js";
import { getSectionById } from "/Module/Section.js";
import { dialog } from "/Dialog/Module/Dialog.js";
import { file } from "/Application/Filemanager/Module/File.js";
import user from "/Module/User.js";

let player = {};

player.init = (id) => {
    taskbar.add('application-audio-player', id);

    player.menu(id);
    player.menu_application(id);
    player.player(id);
    player.close(id);
}

player.close = (id) => {
    let section = getSectionById(id);
    if(!section){
        return;
    }
    let close = section.select('.close');
    close.on('click', (event) => {
        taskbar.delete(section.attribute('id'));
    });
}

player.menu_application = (id) => {
    const section = getSectionById(id);
    if(!section){
        return;
    }
    dialog.click(section, '.menu-application-audio-player');
}

player.menu = (id) => {
    const section = getSectionById(id);
    if(!section){
        return;
    }
    const menu = section.select('.menu');
    if(!menu){
        return;
    }
    const menu_file = menu.select('li.file');
    const menu_file_menu = menu.select('.menu-file');
    const menu_file_protector = menu.select('.menu-file-protector');
    if(menu_file){
        menu_file.on('click', (event) => {
            if(menu_file_menu) {
                menu_file_menu.toggleClass('display-none');
            }
            if(menu_file_protector){
                menu_file_protector.toggleClass('display-none');
            }
        });
    }
    if(menu_file_protector){
        menu_file_protector.on('click', (event) => {
            if(menu_file_menu){
                menu_file_menu.addClass('display-none');
                menu_file_protector.addClass('display-none');
            }
        });
    }
    const menu_file_exit = menu.select('.menu-file-exit');
    if(menu_file_exit){
        menu_file_exit.on('click', (event) => {
            taskbar.delete(section.attribute('id'));
            section.remove();
        });
    }
    const menu_file_open = menu.select('.menu-file-open');
    if(menu_file_open){
        menu_file_open.on('click', (event) => {
            if(menu_file_protector){
                menu_file_protector.trigger('click');
            }
            console.log('need application file manager open url');
            console.log(file);
            /*
            const file_manager_section = getSection(file.data.get('section.id'));
            if(!file_manager_section){
                return;
            }*/
            /*
            const input = file_manager_section.select('input[name="address"]');
            if(!input){
                return;
            }
             */
        });
    }
    dialog.click(section, '.menu');
}

player.player = (id) => {
    const section = getSectionById(id);
    if(!section){
        return;
    }
    let key = user.get('key');
    let src = _('_').str_replace('&', '%26', section.select('input[name="node.source"]')?.value);
    let type = section.select('input[name="node.type"]')?.value;
    if(key){
        src += '&key=' + key;
    }
    let audio = _('_').create('audio');
    audio.crossOrigin = 'anonymous';
    audio.autoplay = true
    audio.controls = true;
    audio.volume = 0.75;
    audio.loop = true;
    let source = _('_').create('source');
    source.src = src;
    source.type = type;
    audio.appendChild(source);
    const body = section.select('.body');
    body.appendChild(audio);
}

export { player }