import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

function supportsWebGL() {
    try {
        const probe = document.createElement('canvas');
        return Boolean(window.WebGLRenderingContext && (probe.getContext('webgl2') || probe.getContext('webgl')));
    } catch {
        return false;
    }
}

function createCeramicBumpTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 256;
    const ctx = canvas.getContext('2d');
    const image = ctx.createImageData(256, 256);
    const data = image.data;
    for (let i = 0; i < data.length; i += 4) {
        const y = Math.floor(i / 4 / 256);
        const latheBand = Math.sin(y * 0.24) * 5;
        const grain = (Math.random() - 0.5) * 14;
        const val = Math.max(0, Math.min(255, Math.round(128 + latheBand + grain)));
        data[i] = val;
        data[i + 1] = val;
        data[i + 2] = val;
        data[i + 3] = 255;
    }
    ctx.putImageData(image, 0, 0);
    const texture = new THREE.CanvasTexture(canvas);
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    texture.repeat.set(3, 2);
    return texture;
}

function createCremaTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 512;
    const ctx = canvas.getContext('2d');
    const cx = 256;
    const cy = 256;

    const baseGrad = ctx.createRadialGradient(cx, cy, 18, cx, cy, 256);
    baseGrad.addColorStop(0.0, '#1B110B');
    baseGrad.addColorStop(0.58, '#2C190F');
    baseGrad.addColorStop(0.82, '#7B4C27');
    baseGrad.addColorStop(0.94, '#B9844E');
    baseGrad.addColorStop(1.0, '#D4A56A');
    ctx.fillStyle = baseGrad;
    ctx.fillRect(0, 0, 512, 512);

    ctx.save();
    for (let i = 0; i < 42; i++) {
        const angle = (i / 42) * Math.PI * 2;
        const r = 138 + Math.sin(i * 3.1) * 42;
        ctx.strokeStyle = i % 3 === 0 ? 'rgba(218, 172, 114, 0.22)' : 'rgba(130, 80, 42, 0.28)';
        ctx.lineWidth = 3 + (i % 4);
        ctx.beginPath();
        ctx.arc(cx + Math.cos(angle) * 18, cy + Math.sin(angle) * 18, r, angle, angle + 1.05);
        ctx.stroke();
    }
    for (let i = 0; i < 180; i++) {
        const theta = Math.random() * Math.PI * 2;
        const radius = 155 + Math.random() * 88;
        const x = cx + Math.cos(theta) * radius;
        const y = cy + Math.sin(theta) * radius;
        ctx.fillStyle = `rgba(229, 188, 134, ${(0.08 + Math.random() * 0.16).toFixed(3)})`;
        ctx.beginPath();
        ctx.arc(x, y, 0.8 + Math.random() * 2.2, 0, Math.PI * 2);
        ctx.fill();
    }
    ctx.restore();

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    return texture;
}

function loadLogoDecalTexture(logoUrl, onReady) {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 512;
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;

    if (!logoUrl) {
        return texture;
    }

    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, 512, 512);
        const pad = 76;
        ctx.drawImage(img, pad, pad, 512 - pad * 2, 512 - pad * 2);
        const imgData = ctx.getImageData(0, 0, 512, 512);
        const pixels = imgData.data;

        for (let i = 0; i < pixels.length; i += 4) {
            const r = pixels[i];
            const g = pixels[i + 1];
            const b = pixels[i + 2];
            const a = pixels[i + 3];
            const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
            if (a < 18 || lum < 30) {
                pixels[i + 3] = 0;
            } else {
                const strength = Math.min(1, Math.max(0, (lum - 30) / 180));
                pixels[i] = 210;
                pixels[i + 1] = 176;
                pixels[i + 2] = 119;
                pixels[i + 3] = Math.round(strength * 215);
            }
        }
        ctx.putImageData(imgData, 0, 0);
        texture.needsUpdate = true;
        if (onReady) {
            onReady();
        }
    };
    img.src = logoUrl;
    return texture;
}

function createCoffeeBeanMesh(material) {
    const geometry = new THREE.SphereGeometry(0.088, 22, 16);
    geometry.scale(1.26, 0.56, 0.86);
    const pos = geometry.attributes.position;
    for (let i = 0; i < pos.count; i++) {
        const y = pos.getY(i);
        const z = pos.getZ(i);
        const x = pos.getX(i);
        if (y > 0.008 && Math.abs(z) < 0.022) {
            const groove = (1 - Math.abs(z) / 0.022) * (1 - Math.min(1, Math.abs(x) / 0.11));
            pos.setY(i, y - groove * 0.022);
        }
    }
    geometry.computeVertexNormals();
    const mesh = new THREE.Mesh(geometry, material);
    mesh.castShadow = true;
    mesh.receiveShadow = true;
    return mesh;
}

function createSteamSystem(ribbonCount) {
    const group = new THREE.Group();
    group.position.set(0, 0.62, 0);

    const vertexShader = `
        uniform float uTime;
        uniform float uFormation;
        uniform float uPhase;
        uniform float uTiltX;
        uniform float uFormRole;
        varying vec2 vUv;
        varying float vHeight;

        void main() {
            vUv = uv;
            vHeight = clamp(position.y / 2.25, 0.0, 1.0);
            vec3 pos = position;

            float waveA = sin(pos.y * 2.7 - uTime * 1.18 + uPhase) * 0.095 * (0.3 + vHeight);
            float waveB = cos(pos.y * 1.65 - uTime * 0.82 + uPhase * 1.4) * 0.065 * vHeight;
            pos.x += waveA + waveB;
            pos.z += sin(pos.y * 2.1 - uTime * 0.96 + uPhase * 0.7) * 0.075 * (0.25 + vHeight);

            float formEnvelope = smoothstep(0.08, 0.86, vHeight) * (1.0 - smoothstep(0.86, 1.0, vHeight));
            if (uFormRole < -0.5) {
                float targetX = mix(-0.32, -0.015, vHeight);
                pos.x = mix(pos.x, targetX + waveA * 0.38, uFormation * formEnvelope * 0.82);
                pos.z *= mix(1.0, 0.35, uFormation * 0.7);
            } else if (uFormRole > 0.5 && uFormRole < 1.5) {
                float targetX = mix(0.32, 0.015, vHeight);
                pos.x = mix(pos.x, targetX + waveA * 0.38, uFormation * formEnvelope * 0.82);
                pos.z *= mix(1.0, 0.35, uFormation * 0.7);
            } else if (uFormRole > 1.5) {
                pos.x += sin(uTime * 0.9) * 0.025;
                pos.y += sin(uv.x * 3.14159) * 0.04;
            } else {
                pos.x += uTiltX * vHeight * 0.28;
            }

            gl_Position = projectionMatrix * modelViewMatrix * vec4(pos, 1.0);
        }
    `;

    const fragmentShader = `
        uniform float uTime;
        uniform float uFormation;
        uniform float uPhase;
        uniform float uBaseOpacity;
        uniform float uFormRole;
        varying vec2 vUv;
        varying float vHeight;

        void main() {
            float edgeX = sin(vUv.x * 3.14159265);
            edgeX = pow(max(0.0, edgeX), 1.65);

            float fadeIn = smoothstep(0.0, 0.16, vUv.y);
            float fadeOut = 1.0 - smoothstep(0.54, 1.0, vUv.y);
            float verticalMask = fadeIn * fadeOut;

            float wisp = 0.62 + 0.38 * sin(vUv.y * 9.5 - uTime * 1.45 + uPhase);
            float alpha = edgeX * verticalMask * wisp * uBaseOpacity;

            if (uFormRole > 1.5) {
                float crossBarFade = sin(vUv.x * 3.14159265);
                float verticalSoft = sin(vUv.y * 3.14159265);
                alpha = pow(max(0.0, crossBarFade * verticalSoft), 1.35) * uFormation * uBaseOpacity * (0.8 + 0.2 * sin(uTime * 1.9));
            } else if (abs(uFormRole) > 0.5) {
                alpha *= mix(1.0, 1.18, uFormation);
            }

            vec3 vaporColor = mix(vec3(0.957, 0.933, 0.898), vec3(0.824, 0.690, 0.467), 0.16 * (1.0 - vHeight));
            gl_FragColor = vec4(vaporColor, alpha);
        }
    `;

    const configs = [
        { x: -0.22, z: 0.06, width: 0.24, height: 2.05, phase: 0.2, tilt: -0.18, opacity: 0.19, role: -1, rotY: 0.18 },
        { x: 0.22, z: -0.04, width: 0.24, height: 2.05, phase: 2.1, tilt: 0.18, opacity: 0.19, role: 1, rotY: -0.18 },
        { x: 0.0, z: 0.14, width: 0.34, height: 2.25, phase: 3.7, tilt: 0.05, opacity: 0.14, role: 0, rotY: 0.45 },
        { x: -0.11, z: -0.14, width: 0.28, height: 1.85, phase: 5.0, tilt: -0.12, opacity: 0.12, role: 0, rotY: -0.52 },
        { x: 0.14, z: 0.11, width: 0.22, height: 1.72, phase: 1.2, tilt: 0.15, opacity: 0.11, role: 0, rotY: 0.78 },
        { x: 0.0, z: 0.02, width: 0.44, height: 0.16, phase: 2.8, tilt: 0.0, opacity: 0.17, role: 2, rotY: 0.0, yOffset: 0.86 },
    ];

    const materials = [];
    const geometries = [];

    const activeConfigs = ribbonCount <= 4
        ? [configs[0], configs[1], configs[2], configs[5]]
        : configs;

    for (const cfg of activeConfigs) {
        const segY = cfg.role === 2 ? 10 : 32;
        const segX = cfg.role === 2 ? 18 : 6;
        const geo = new THREE.PlaneGeometry(cfg.width, cfg.height, segX, segY);
        if (cfg.role !== 2) {
            geo.translate(0, cfg.height * 0.5, 0);
        }
        const mat = new THREE.ShaderMaterial({
            uniforms: {
                uTime: { value: 0 },
                uFormation: { value: 0 },
                uPhase: { value: cfg.phase },
                uTiltX: { value: cfg.tilt },
                uBaseOpacity: { value: cfg.opacity },
                uFormRole: { value: cfg.role },
            },
            vertexShader,
            fragmentShader,
            transparent: true,
            depthWrite: false,
            side: THREE.DoubleSide,
        });
        const mesh = new THREE.Mesh(geo, mat);
        mesh.position.set(cfg.x, cfg.yOffset ?? 0, cfg.z);
        mesh.rotation.y = cfg.rotY;
        group.add(mesh);
        geometries.push(geo);
        materials.push(mat);
    }

    const update = (elapsed, reducedMotion) => {
        if (reducedMotion) {
            for (const mat of materials) {
                mat.uniforms.uTime.value = 1.6;
                mat.uniforms.uFormation.value = 0.0;
            }
            return;
        }
        const cycle = (elapsed % 11.0) / 11.0;
        let formation = 0;
        if (cycle > 0.24 && cycle < 0.64) {
            const t = (cycle - 0.24) / 0.40;
            formation = Math.sin(t * Math.PI);
            formation = formation * formation;
        }
        for (const mat of materials) {
            mat.uniforms.uTime.value = elapsed;
            mat.uniforms.uFormation.value = formation;
        }
    };

    return { group, geometries, materials, update };
}

export function createCupScene(container, options = {}) {
    if (!container || !supportsWebGL()) {
        return null;
    }

    const reducedMotion = Boolean(options.reducedMotion);
    const width = Math.max(container.clientWidth, 300);
    const height = Math.max(container.clientHeight, 320);
    const isLowPower = (navigator.hardwareConcurrency || 8) <= 4;

    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({
            antialias: true,
            alpha: true,
            powerPreference: 'high-performance',
        });
    } catch {
        return null;
    }

    const getPixelRatioCap = () => {
        const narrow = window.innerWidth < 768;
        return Math.min(window.devicePixelRatio || 1, narrow || isLowPower ? 1.5 : 2);
    };

    renderer.setPixelRatio(getPixelRatioCap());
    renderer.setSize(width, height, false);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.18;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.domElement.className = 'block w-full h-full';
    renderer.domElement.setAttribute('aria-hidden', 'true');
    container.innerHTML = '';
    container.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(34, width / height, 0.1, 30);

    const pmremGenerator = new THREE.PMREMGenerator(renderer);
    const envScene = new RoomEnvironment();
    const envTarget = pmremGenerator.fromScene(envScene, 0.04);
    scene.environment = envTarget.texture;
    if ('environmentIntensity' in scene) {
        scene.environmentIntensity = 0.34;
    }

    const disposables = {
        geometries: [],
        materials: [],
        textures: [envTarget.texture],
    };

    const ceramicBump = createCeramicBumpTexture();
    const cremaTex = createCremaTexture();
    disposables.textures.push(ceramicBump, cremaTex);

    const studioMugColor = new THREE.Color(22 / 255, 20 / 255, 18 / 255);
    const ceramicMaterial = new THREE.MeshPhysicalMaterial({
        color: studioMugColor,
        roughness: 0.36,
        metalness: 0.04,
        clearcoat: 0.42,
        clearcoatRoughness: 0.28,
        bumpMap: ceramicBump,
        bumpScale: 0.0025,
    });
    const saucerMaterial = new THREE.MeshPhysicalMaterial({
        color: studioMugColor,
        roughness: 0.38,
        metalness: 0.04,
        clearcoat: 0.36,
        clearcoatRoughness: 0.32,
        bumpMap: ceramicBump,
        bumpScale: 0.0022,
    });
    const goldAccentMaterial = new THREE.MeshStandardMaterial({
        color: 0xd2b077,
        metalness: 0.74,
        roughness: 0.30,
    });
    const coffeeMaterial = new THREE.MeshPhysicalMaterial({
        map: cremaTex,
        roughness: 0.16,
        metalness: 0.02,
        clearcoat: 0.68,
        clearcoatRoughness: 0.14,
    });
    const beanMaterial = new THREE.MeshStandardMaterial({
        color: 0x3b2314,
        roughness: 0.44,
        metalness: 0.05,
    });
    const shadowMaterial = new THREE.ShadowMaterial({ opacity: 0.44 });

    disposables.materials.push(
        ceramicMaterial,
        saucerMaterial,
        goldAccentMaterial,
        coffeeMaterial,
        beanMaterial,
        shadowMaterial
    );

    const sceneRoot = new THREE.Group();
    const cupAssembly = new THREE.Group();
    sceneRoot.add(cupAssembly);
    scene.add(sceneRoot);

    const cupPoints = [
        new THREE.Vector2(0.0, 0.025),
        new THREE.Vector2(0.46, 0.025),
        new THREE.Vector2(0.50, 0.065),
        new THREE.Vector2(0.60, 0.22),
        new THREE.Vector2(0.71, 0.56),
        new THREE.Vector2(0.75, 0.90),
        new THREE.Vector2(0.76, 1.01),
        new THREE.Vector2(0.725, 1.035),
        new THREE.Vector2(0.692, 1.00),
        new THREE.Vector2(0.64, 0.60),
        new THREE.Vector2(0.52, 0.24),
        new THREE.Vector2(0.42, 0.115),
        new THREE.Vector2(0.0, 0.105),
    ];
    const cupGeo = new THREE.LatheGeometry(cupPoints, 72);
    const cupMesh = new THREE.Mesh(cupGeo, ceramicMaterial);
    cupMesh.castShadow = true;
    cupMesh.receiveShadow = true;
    cupAssembly.add(cupMesh);
    disposables.geometries.push(cupGeo);

    const handleCurve = new THREE.CatmullRomCurve3([
        new THREE.Vector3(0.69, 0.84, 0.0),
        new THREE.Vector3(1.06, 0.84, 0.0),
        new THREE.Vector3(1.13, 0.56, 0.0),
        new THREE.Vector3(1.02, 0.30, 0.0),
        new THREE.Vector3(0.59, 0.28, 0.0),
    ]);
    const handleGeo = new THREE.TubeGeometry(handleCurve, 44, 0.066, 20, false);
    handleGeo.scale(1, 1, 0.76);
    const handleMesh = new THREE.Mesh(handleGeo, ceramicMaterial);
    handleMesh.castShadow = true;
    handleMesh.receiveShadow = true;
    cupAssembly.add(handleMesh);
    disposables.geometries.push(handleGeo);

    const logoTexture = loadLogoDecalTexture(options.logoUrl, () => {
        if (reducedMotion) {
            renderOnce();
        }
    });
    disposables.textures.push(logoTexture);

    const decalMaterial = new THREE.MeshPhysicalMaterial({
        map: logoTexture,
        transparent: true,
        opacity: 0.92,
        roughness: 0.34,
        metalness: 0.38,
        clearcoat: 0.35,
        depthWrite: false,
        polygonOffset: true,
        polygonOffsetFactor: -1,
    });
    disposables.materials.push(decalMaterial);

    const decalGeo = new THREE.CylinderGeometry(0.718, 0.652, 0.42, 36, 1, true, -0.44, 0.88);
    const decalMesh = new THREE.Mesh(decalGeo, decalMaterial);
    decalMesh.position.y = 0.57;
    decalMesh.rotation.y = 0.34;
    cupAssembly.add(decalMesh);
    disposables.geometries.push(decalGeo);

    const coffeeGeo = new THREE.CircleGeometry(0.688, 64);
    coffeeGeo.rotateX(-Math.PI / 2);
    const coffeeMesh = new THREE.Mesh(coffeeGeo, coffeeMaterial);
    coffeeMesh.position.y = 0.915;
    coffeeMesh.receiveShadow = true;
    cupAssembly.add(coffeeMesh);
    disposables.geometries.push(coffeeGeo);

    const saucerPoints = [
        new THREE.Vector2(0.0, -0.075),
        new THREE.Vector2(0.58, -0.075),
        new THREE.Vector2(0.92, -0.042),
        new THREE.Vector2(1.22, 0.042),
        new THREE.Vector2(1.28, 0.072),
        new THREE.Vector2(1.255, 0.088),
        new THREE.Vector2(1.16, 0.068),
        new THREE.Vector2(0.84, 0.012),
        new THREE.Vector2(0.50, -0.008),
        new THREE.Vector2(0.45, 0.022),
        new THREE.Vector2(0.0, 0.022),
    ];
    const saucerGeo = new THREE.LatheGeometry(saucerPoints, 72);
    const saucerMesh = new THREE.Mesh(saucerGeo, saucerMaterial);
    saucerMesh.castShadow = true;
    saucerMesh.receiveShadow = true;
    cupAssembly.add(saucerMesh);
    disposables.geometries.push(saucerGeo);

    const goldRingGeo = new THREE.TorusGeometry(1.262, 0.0075, 10, 72);
    goldRingGeo.rotateX(Math.PI / 2);
    const goldRingMesh = new THREE.Mesh(goldRingGeo, goldAccentMaterial);
    goldRingMesh.position.y = 0.079;
    cupAssembly.add(goldRingMesh);
    disposables.geometries.push(goldRingGeo);

    const beanSpots = [
        { pos: [-0.82, 0.048, 0.56], rot: [0.16, 0.52, -0.10], scale: 1.0 },
        { pos: [-0.95, 0.064, 0.36], rot: [-0.22, 1.38, 0.14], scale: 0.92 },
        { pos: [0.88, 0.056, 0.62], rot: [0.12, -0.78, 0.18], scale: 0.96 },
        { pos: [-1.18, -0.042, 0.96], rot: [0.05, 0.92, -0.08], scale: 1.06 },
        { pos: [-0.96, -0.042, 1.12], rot: [-0.08, -0.44, 0.12], scale: 0.94 },
    ];
    for (const spot of beanSpots) {
        const bean = createCoffeeBeanMesh(beanMaterial);
        bean.position.set(spot.pos[0], spot.pos[1], spot.pos[2]);
        bean.rotation.set(spot.rot[0], spot.rot[1], spot.rot[2]);
        bean.scale.setScalar(spot.scale);
        cupAssembly.add(bean);
        disposables.geometries.push(bean.geometry);
    }

    const groundGeo = new THREE.PlaneGeometry(8, 8);
    groundGeo.rotateX(-Math.PI / 2);
    const groundMesh = new THREE.Mesh(groundGeo, shadowMaterial);
    groundMesh.position.y = -0.082;
    groundMesh.receiveShadow = true;
    cupAssembly.add(groundMesh);
    disposables.geometries.push(groundGeo);

    const steam = createSteamSystem(isLowPower || window.innerWidth < 768 ? 4 : 6);
    cupAssembly.add(steam.group);
    disposables.geometries.push(...steam.geometries);
    disposables.materials.push(...steam.materials);

    const hemiLight = new THREE.HemisphereLight(0xf1e1cb, 0x161412, 0.60);
    scene.add(hemiLight);

    const keyLight = new THREE.DirectionalLight(0xffe1b8, 2.35);
    keyLight.position.set(-2.9, 4.4, 3.2);
    keyLight.castShadow = true;
    const shadowSize = isLowPower || window.innerWidth < 768 ? 512 : 1024;
    keyLight.shadow.mapSize.set(shadowSize, shadowSize);
    keyLight.shadow.camera.near = 1.0;
    keyLight.shadow.camera.far = 11.0;
    keyLight.shadow.camera.left = -2.2;
    keyLight.shadow.camera.right = 2.2;
    keyLight.shadow.camera.top = 2.2;
    keyLight.shadow.camera.bottom = -2.2;
    keyLight.shadow.bias = -0.0005;
    scene.add(keyLight);

    const rimLight = new THREE.SpotLight(0xd2b077, 14.0, 12, Math.PI * 0.34, 0.65, 1.5);
    rimLight.position.set(3.2, 2.9, -2.5);
    rimLight.target.position.set(0, 0.48, 0);
    scene.add(rimLight);
    scene.add(rimLight.target);

    const fillLight = new THREE.PointLight(0xa37345, 2.4, 8, 1.6);
    fillLight.position.set(2.1, 0.55, 2.6);
    scene.add(fillLight);

    const updateLayout = () => {
        const w = Math.max(container.clientWidth, 280);
        const h = Math.max(container.clientHeight, 300);
        const desktop = window.innerWidth >= 1024;

        camera.aspect = w / h;
        camera.fov = desktop ? 33 : 36;
        camera.position.set(desktop ? -0.45 : 0, desktop ? 1.85 : 2.02, desktop ? 4.45 : 4.95);
        camera.lookAt(desktop ? -0.15 : 0, 0.58, 0);
        camera.updateProjectionMatrix();

        renderer.setPixelRatio(getPixelRatioCap());
        renderer.setSize(w, h, false);
    };

    updateLayout();

    let targetPointerX = 0;
    let targetPointerY = 0;
    let currentPointerX = 0;
    let currentPointerY = 0;
    let scrollProgress = 0;
    let smoothScroll = 0;
    let entranceProgress = reducedMotion ? 1 : 0;
    let isDisposed = false;
    let isVisible = true;
    let isTabVisible = !document.hidden;
    let rafId = null;
    const clock = new THREE.Clock();

    const applyPose = (elapsed) => {
        currentPointerX += (targetPointerX - currentPointerX) * 0.08;
        currentPointerY += (targetPointerY - currentPointerY) * 0.08;
        smoothScroll += (scrollProgress - smoothScroll) * 0.10;

        const e = Math.max(0, Math.min(1, entranceProgress));
        const idleSway = reducedMotion ? 0 : Math.sin(elapsed * 0.52) * 0.045;
        const idleBob = reducedMotion ? 0 : Math.sin(elapsed * 0.85) * 0.028;

        cupAssembly.position.y = (1 - e) * -0.28 + idleBob - smoothScroll * 0.22;
        cupAssembly.position.x = smoothScroll * 0.28;
        cupAssembly.rotation.y = -0.34 + (1 - e) * -0.22 + currentPointerX * 0.24 + smoothScroll * 0.72 + idleSway;
        cupAssembly.rotation.x = 0.04 + currentPointerY * 0.09 + smoothScroll * 0.16;
        cupAssembly.scale.setScalar(0.92 + e * 0.08);

        steam.update(elapsed, reducedMotion);
    };

    const renderOnce = () => {
        if (isDisposed) {
            return;
        }
        applyPose(clock.getElapsedTime());
        renderer.render(scene, camera);
    };

    const tick = () => {
        if (isDisposed || !isVisible || !isTabVisible || reducedMotion) {
            rafId = null;
            return;
        }
        renderOnce();
        rafId = window.requestAnimationFrame(tick);
    };

    const startLoop = () => {
        if (isDisposed || reducedMotion || rafId !== null || !isVisible || !isTabVisible) {
            return;
        }
        clock.start();
        rafId = window.requestAnimationFrame(tick);
    };

    const stopLoop = () => {
        if (rafId !== null) {
            window.cancelAnimationFrame(rafId);
            rafId = null;
        }
    };

    const resizeObserver = new ResizeObserver(() => {
        updateLayout();
        if (reducedMotion || !rafId) {
            renderOnce();
        }
    });
    resizeObserver.observe(container);

    const intersectionObserver = new IntersectionObserver(
        (entries) => {
            const entry = entries[0];
            isVisible = Boolean(entry && entry.isIntersecting);
            if (isVisible) {
                startLoop();
            } else {
                stopLoop();
            }
        },
        { threshold: 0.02 }
    );
    intersectionObserver.observe(container);

    const onVisibilityChange = () => {
        isTabVisible = !document.hidden;
        if (isTabVisible) {
            startLoop();
        } else {
            stopLoop();
        }
    };
    document.addEventListener('visibilitychange', onVisibilityChange);

    renderOnce();
    if (!reducedMotion) {
        startLoop();
    }

    return {
        setPointer(nx, ny) {
            targetPointerX = Math.max(-1, Math.min(1, nx));
            targetPointerY = Math.max(-1, Math.min(1, ny));
        },
        setScrollProgress(progress) {
            scrollProgress = Math.max(0, Math.min(1, progress));
            if (reducedMotion) {
                renderOnce();
            }
        },
        setEntranceProgress(progress) {
            entranceProgress = Math.max(0, Math.min(1, progress));
            if (reducedMotion) {
                renderOnce();
            }
        },
        resize() {
            updateLayout();
            renderOnce();
        },
        dispose() {
            if (isDisposed) {
                return;
            }
            isDisposed = true;
            stopLoop();
            resizeObserver.disconnect();
            intersectionObserver.disconnect();
            document.removeEventListener('visibilitychange', onVisibilityChange);
            for (const geometry of disposables.geometries) {
                geometry.dispose();
            }
            for (const material of disposables.materials) {
                material.dispose();
            }
            for (const texture of disposables.textures) {
                texture.dispose();
            }
            envScene.dispose();
            pmremGenerator.dispose();
            renderer.dispose();
        },
    };
}
