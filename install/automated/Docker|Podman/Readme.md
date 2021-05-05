Instruction for building with **Podman|Docker**:

For **podman** use:

1. podman build -t openvk .
2. podman run -it -p YOUR_EXTEND_PORT:80 openvk




For **docker** use:

1. docker build -t openvk .
2. docker run -it -p YOUR_EXTEND_PORT:80 --privileged openvk
