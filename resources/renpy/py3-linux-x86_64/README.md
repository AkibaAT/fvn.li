# Ren'Py Binary Distribution

This directory contains a distribution of Ren'Py, which is used by FVN.li for parsing and analyzing visual novel data.

## License Information

Most of Ren'Py is covered under the MIT license, but portions are derived from source code licensed under the GNU Lesser General Public License (LGPL), so Ren'Py games must be distributed in a manner that satisfies the LGPL.

The official license information can be found at: https://www.renpy.org/doc/html/license.html

For the purposes of LGPL compliance, all source code that Ren'Py depends on is located in one of the following repositories:

* https://github.com/renpy/renpy (Ren'Py)
* https://github.com/renpy/pygame_sdl2 (Pygame_SDL2)
* https://github.com/renpy/renpy-build (Dependencies)
* https://github.com/renpy/renpyweb (Web)

## Source Code and Building Information

Ren'Py is an open-source project, and its source code is freely available.

- Official Ren'Py website: https://www.renpy.org/
- Source code repository: https://github.com/renpy/renpy/
- Build instructions: https://github.com/renpy/renpy-build/

## Building Ren'Py

To build Ren'Py from source, you'll need a Linux development environment with the following tools:

1. Python 3.9 or later
2. Various development libraries (SDL2, FFMPEG, etc.)
3. Cython

The basic build process involves:

```bash
# Clone the repository
git clone https://github.com/renpy/renpy-build.git
cd renpy-build

# Install build dependencies
./prepare.sh

# Build Ren'Py
. tmp/virtualenv.py3/bin/activate
./build.py
```

For detailed and up-to-date build instructions, please refer to the official documentation at the link provided above.

## Version Information

The version of Ren'Py included in this directory is a pre-compiled binary distribution for Linux x86_64 platforms with Python 3 support. This distribution is used by FVN.li to analyze game data for Ren'Py games shipping without Linux binaries.

## Attribution Statement

This program contains free software licensed under a number of licenses, including the GNU Lesser General Public License. A complete list of software is available at http://www.renpy.org/doc/html/license.html. 
